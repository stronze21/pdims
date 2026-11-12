function normalizeRemarks(value) {
    return value.trim().toLowerCase().replace('insctructions', 'instructions');
}

document.addEventListener('alpine:init', () => {
    Alpine.data('prescriptionEntryTraining', (medication, source, sampleRemarks) => ({
        screen: 'list',
        query: '',
        itemAdded: false,
        quantity: '',
        frequency: '',
        days: '',
        remarks: '',
        proceedToPharmacy: false,
        filter: 'All',

        get hasResult() {
            const query = this.query.trim().toLowerCase();
            return query.length >= (source === 'emr2' ? 2 : 3) && medication.toLowerCase().startsWith(query);
        },

        get stage() {
            if (this.screen === 'list') return 'rx-entry-start';
            if (this.screen === 'care') return 'rx-entry-care';
            if (this.screen === 'queued') return 'rx-entry-handoff';
            if (!this.itemAdded) return this.hasResult ? 'rx-entry-add' : 'rx-entry-search';
            if (this.quantity !== 1) return 'rx-entry-quantity';
            if (!this.frequency) return 'rx-entry-frequency';
            if (this.days !== 1) return 'rx-entry-days';
            if (normalizeRemarks(this.remarks) !== normalizeRemarks(sampleRemarks)) return 'rx-entry-remarks';
            return this.proceedToPharmacy ? 'rx-entry-submit' : 'rx-entry-queue';
        },

        startPrescription() {
            this.screen = 'form';
            this.$nextTick(() => this.$refs.search.focus());
        },

        addItem() {
            if (!this.hasResult) return;
            this.itemAdded = true;
            this.$nextTick(() => this.$refs.quantity.focus());
        },

        removeItem() {
            this.itemAdded = false;
            this.quantity = '';
            this.frequency = '';
            this.days = '';
            this.remarks = '';
            this.proceedToPharmacy = false;
            this.$nextTick(() => this.$refs.search.focus());
        },

        submit() {
            if (this.stage !== 'rx-entry-submit') return;
            if (source === 'emr2') {
                this.screen = 'care';
                return;
            }
            this.finish();
        },

        finish() {
            this.screen = 'queued';
            this.$nextTick(() => this.$refs.handoff.focus());
        },
    }));
});
