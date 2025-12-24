<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CommandExecution;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * ScheduleCallbackController
 * 
 * Receives progress callbacks from RAI instances during schedule execution.
 * Updates CommandExecution records with status, progress, and output.
 */
class ScheduleCallbackController extends Controller
{
    /**
     * Maximum allowed timestamp drift in seconds (5 minutes)
     */
    private const MAX_TIMESTAMP_DRIFT = 300;

    /**
     * Handle incoming callback from RAI
     * 
     * Expected payload:
     * {
     *   "execution_id": 123,
     *   "status": "running|completed|failed",
     *   "current_step": "toast:fetch-employees",
     *   "completed_steps": 5,
     *   "total_steps": 10,
     *   "output": "Command output log...",
     *   "error": null,
     *   "completed_at": "2024-12-24T10:30:00Z"
     * }
     */
    public function handle(Request $request)
    {
        // 1. Validate webhook signature
        $signatureValidation = $this->validateSignature($request);
        if ($signatureValidation !== true) {
            Log::warning('Schedule callback signature validation failed', [
                'reason' => $signatureValidation,
                'ip' => $request->ip(),
            ]);
            return response()->json(['error' => 'Invalid signature'], 401);
        }

        // 2. Get execution ID from payload
        $executionId = $request->input('execution_id');
        if (!$executionId) {
            return response()->json(['error' => 'Missing execution_id'], 400);
        }

        // 3. Find execution record
        $execution = CommandExecution::find($executionId);
        if (!$execution) {
            Log::warning('Schedule callback: execution not found', ['execution_id' => $executionId]);
            return response()->json(['error' => 'Execution not found'], 404);
        }

        // 4. Update execution record based on callback data
        $updateData = [];

        if ($request->has('status')) {
            $updateData['status'] = $request->input('status');
        }

        if ($request->has('current_step')) {
            $updateData['current_step'] = $request->input('current_step');
        }

        if ($request->has('completed_steps')) {
            $updateData['completed_steps'] = $request->input('completed_steps');
        }

        if ($request->has('total_steps')) {
            $updateData['total_steps'] = $request->input('total_steps');
        }

        if ($request->has('output')) {
            $updateData['output'] = $request->input('output');
        }

        if ($request->has('error')) {
            $updateData['error'] = $request->input('error');
        }

        if ($request->has('completed_at')) {
            $updateData['completed_at'] = $request->input('completed_at');
        }

        if ($request->has('pid')) {
            $updateData['process_id'] = $request->input('pid');
        }

        if (!empty($updateData)) {
            $execution->update($updateData);
            
            Log::debug('Schedule callback processed', [
                'execution_id' => $executionId,
                'status' => $updateData['status'] ?? $execution->status,
                'completed_steps' => $updateData['completed_steps'] ?? $execution->completed_steps,
            ]);
        }

        return response()->json(['success' => true]);
    }

    /**
     * Validate HMAC signature from request header
     * 
     * @return true|string True if valid, error message if invalid
     */
    private function validateSignature(Request $request)
    {
        $secret = config('services.rai.webhook_secret');
        
        if (empty($secret)) {
            return 'Webhook secret not configured';
        }

        $signatureHeader = $request->header('X-Webhook-Signature');
        if (empty($signatureHeader)) {
            return 'Missing signature header';
        }

        // Parse header: timestamp.signature
        $parts = explode('.', $signatureHeader, 2);
        if (count($parts) !== 2) {
            return 'Invalid signature format';
        }

        [$timestamp, $signature] = $parts;

        // Validate timestamp (within 5 minutes)
        $now = time();
        $requestTime = (int) $timestamp;
        
        if (abs($now - $requestTime) > self::MAX_TIMESTAMP_DRIFT) {
            return 'Timestamp expired';
        }

        // Calculate expected signature
        $payload = $request->getContent();
        $expectedSignature = hash_hmac('sha256', $timestamp . '.' . $payload, $secret);

        // Constant-time comparison to prevent timing attacks
        if (!hash_equals($expectedSignature, $signature)) {
            return 'Signature mismatch';
        }

        return true;
    }
}

