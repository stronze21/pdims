window.udddsBatchLoader = function (wire, codes) {
    return {
        codes: codes, next: 0, loading: false, modalOpen: codes.length > 0,
        error: '', ready: false,
        async load() {
            if (this.loading || this.ready || !this.codes.length) return;
            this.loading = true;
            this.modalOpen = true;
            this.error = '';
            try {
                while (this.next < this.codes.length) {
                    const result = await wire.loadSlip(this.codes[this.next]);
                    if (!result || !result.ok || !result.html) {
                        throw new Error(result && result.message ? result.message : 'Unable to load this charge slip.');
                    }
                    this.$refs.receipts.insertAdjacentHTML('beforeend', result.html);
                    this.next++;
                }
                this.ready = true;
                this.modalOpen = false;
                await this.$nextTick();
                if (document.fonts) await document.fonts.ready;
                await new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve)));
                this.print();
            } catch (error) {
                this.error = 'Could not load ' + this.codes[this.next] + '. ' +
                    (error instanceof Error && error.message ? error.message : 'Please retry.');
            } finally {
                this.loading = false;
            }
        },
        print() {
            if (this.ready && this.next === this.codes.length && this.codes.length > 0) window.print();
        }
    };
};
