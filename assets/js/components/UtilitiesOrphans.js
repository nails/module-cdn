class UtilitiesOrphans {
    constructor() {
        if ($('#cdn-orphan-search-form').length) {
            $('#cdn-orphan-search-form').on('submit', () => {
                $('#cdn-orphan-search-mask').show();
            });
        }
    }
}

export default UtilitiesOrphans;
