<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\RdsInstance;
use App\Models\TenantMaster;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * RaiWebhookController
 * 
 * Receives webhook events from RAI instances.
 * Used to push audit events from RAI back to RAINBO for centralized logging.
 */
class RaiWebhookController extends Controller
{
    /**
     * Handle incoming audit event from RAI
     * 
     * Expected payload:
     * {
     *   "event_type": "audit",
     *   "rds_instance_id": 1,
     *   "data": {
     *     "action": "updated",
     *     "model_type": "User",
     *     "model_id": 123,
     *     "tenant_id": 5,
     *     "rainbo_admin_id": 1,  // If from RAINBO session
     *     "old_values": {...},
     *     "new_values": {...},
     *     "ip_address": "1.2.3.4",
     *     "user_agent": "...",
     *     "timestamp": "2025-12-20T15:30:00Z"
     *   }
     * }
     */
    public function handleAuditEvent(Request $request): JsonResponse
    {
        // Validate webhook signature
        if (!$this->validateSignature($request)) {
            Log::warning('RAI webhook: invalid signature', [
                'ip' => $request->ip(),
            ]);
            return response()->json(['error' => 'Invalid signature'], 401);
        }

        $validated = $request->validate([
            'event_type' => 'required|string|in:audit',
            'rds_instance_id' => 'required|integer',
            'data' => 'required|array',
            'data.action' => 'required|string',
            'data.model_type' => 'nullable|string',
            'data.model_id' => 'nullable|integer',
            'data.tenant_id' => 'nullable|integer',
            'data.rainbo_admin_id' => 'nullable|integer',
            'data.old_values' => 'nullable|array',
            'data.new_values' => 'nullable|array',
            'data.ip_address' => 'nullable|string',
            'data.user_agent' => 'nullable|string',
            'data.timestamp' => 'nullable|string',
        ]);

        try {
            // Verify RDS instance exists
            $rds = RdsInstance::find($validated['rds_instance_id']);
            if (!$rds) {
                Log::warning('RAI webhook: unknown RDS instance', [
                    'rds_id' => $validated['rds_instance_id'],
                ]);
                return response()->json(['error' => 'Unknown RDS instance'], 400);
            }

            $data = $validated['data'];

            // Find tenant_master_id from remote tenant_id
            $tenantMasterId = null;
            if (!empty($data['tenant_id'])) {
                $tenant = TenantMaster::where('rds_instance_id', $rds->id)
                    ->where('remote_tenant_id', $data['tenant_id'])
                    ->first();
                $tenantMasterId = $tenant?->id;
            }

            // Create audit log entry
            AuditLog::create([
                'rainbo_user_id' => $data['rainbo_admin_id'] ?? null,
                'action' => $data['action'],
                'model_type' => $data['model_type'] ?? null,
                'model_id' => $data['model_id'] ?? null,
                'rds_instance_id' => $rds->id,
                'tenant_master_id' => $tenantMasterId,
                'old_values' => $data['old_values'] ?? null,
                'new_values' => $data['new_values'] ?? null,
                'ip_address' => $data['ip_address'] ?? null,
                'user_agent' => $data['user_agent'] ?? null,
                'source' => 'rai_push',
                'created_at' => isset($data['timestamp']) 
                    ? \Carbon\Carbon::parse($data['timestamp']) 
                    : now(),
            ]);

            Log::info('RAI webhook: audit event recorded', [
                'rds' => $rds->name,
                'action' => $data['action'],
                'model_type' => $data['model_type'] ?? null,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Audit event recorded',
            ]);

        } catch (\Exception $e) {
            Log::error('RAI webhook: failed to process audit event', [
                'error' => $e->getMessage(),
                'rds_id' => $validated['rds_instance_id'] ?? null,
            ]);

            return response()->json([
                'error' => 'Failed to process event',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Health check endpoint for RAI to verify webhook connectivity
     */
    public function healthCheck(Request $request): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'timestamp' => now()->toIso8601String(),
            'app' => 'RAINBO',
        ]);
    }

    /**
     * Validate webhook signature
     * 
     * RAI should send a signature in the X-Rainbo-Signature header:
     * signature = HMAC-SHA256(request_body, webhook_secret)
     */
    protected function validateSignature(Request $request): bool
    {
        $secret = config('rainbo.webhook_secret');

        // If no secret configured, skip validation (development mode)
        if (empty($secret)) {
            Log::warning('RAI webhook: no webhook secret configured, skipping signature validation');
            return true;
        }

        $signature = $request->header('X-Rainbo-Signature');
        
        if (empty($signature)) {
            return false;
        }

        $expectedSignature = hash_hmac('sha256', $request->getContent(), $secret);

        return hash_equals($expectedSignature, $signature);
    }
}

