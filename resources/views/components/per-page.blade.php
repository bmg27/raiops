<div class="d-flex align-items-center mb-2 mt-4 col-sm-12"
     x-data="{
         perPage: $persist(25).as('{{ request()->route()->getName() }}_perPage'),
         init() {
             this.$nextTick(() => {
                 if (this.perPage) {
                     $wire.set('perPage', this.perPage);
                 }
             });
         }
     }">

    <select x-model="perPage"
            @change="$wire.set('perPage', perPage)"
            class="form-select form-select-sm w-auto">
        <option value="10">10</option>
        <option value="25">25</option>
        <option value="50">50</option>
        <option value="100">100</option>
    </select>

    <label class="me-2 ps-2">
        <small class="text-muted">Records per page.</small>
    </label>
</div>
