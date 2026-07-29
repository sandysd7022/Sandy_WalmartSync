define([
    'jquery'
], function ($) {
    'use strict';

    var attentionClasses = [
        'walmart-cell-mapping',
        'walmart-cell-control',
        'walmart-cell-result',
        'walmart-cell-reference'
    ].join(' ');

    var columnClasses = {
        'mapping type': 'walmart-cell-mapping',
        'mapping verified': 'walmart-cell-mapping',
        'ready to sync': 'walmart-cell-mapping',
        'eligibility reason': 'walmart-cell-mapping',
        'published status': 'walmart-cell-mapping',

        'last known qty': 'walmart-cell-control',
        'magento sku': 'walmart-cell-control',
        'sync enabled': 'walmart-cell-control',
        'magento qty': 'walmart-cell-control',
        'meltable': 'walmart-cell-control',
        'seasonal status': 'walmart-cell-control',
        'calculated walmart qty': 'walmart-cell-control',
        'sync action': 'walmart-cell-control',
        'last sync time': 'walmart-cell-control',

        'last result': 'walmart-cell-result',
        'last error': 'walmart-cell-result',

        'updated': 'walmart-cell-reference',
        'exemption history': 'walmart-cell-reference'
    };

    function normalizeLabel(value) {
        return $.trim(value).replace(/\s+/g, ' ').toLowerCase();
    }

    function applyAttentionClasses() {
        $('.admin__data-grid-wrap table').each(function () {
            var $table = $(this);
            var columnIndexes = {};
            var $headerRow = $table.find('thead tr').filter(function () {
                return normalizeLabel($(this).text()).indexOf('walmart sku') !== -1;
            }).first();

            if (!$headerRow.length) {
                $headerRow = $table.find('thead tr').first();
            }

            $headerRow.children('th').each(function (index) {
                var $header = $(this);
                var label = normalizeLabel($header.text());
                var className = columnClasses[label];

                columnIndexes[label] = index;

                $header.removeClass(attentionClasses);
                $table.find('tbody tr').each(function () {
                    $(this).children('td').eq(index).removeClass(attentionClasses);
                });

                if (!className) {
                    return;
                }

                $header.addClass(className);
                $table.find('tbody tr').each(function () {
                    $(this).children('td').eq(index).addClass(className);
                });
            });

            if (columnIndexes['mapping type'] === undefined ||
                columnIndexes['mapping verified'] === undefined) {
                return;
            }

            $table.find('tbody tr').each(function () {
                var $cells = $(this).children('td');
                var mappingType = normalizeLabel(
                    $cells.eq(columnIndexes['mapping type']).text()
                );
                var $verified = $cells.eq(columnIndexes['mapping verified']);
                var verifiedValue = normalizeLabel($verified.text());
                var $syncEnabled;
                var syncValue;

                $verified.removeClass(
                    'walmart-verification-ok walmart-verification-review walmart-verification-na'
                );

                if (mappingType === 'product_sku') {
                    if (verifiedValue !== 'not required') {
                        $verified.text('Not required');
                    }
                    $verified
                        .addClass('walmart-verification-na')
                        .attr('title', 'Direct product SKU mappings do not require manual verification.');
                } else if (mappingType === 'custom_option') {
                    if (verifiedValue === 'yes' || verifiedValue === 'verified') {
                        if (verifiedValue !== 'verified') {
                            $verified.text('Verified');
                        }
                        $verified
                            .addClass('walmart-verification-ok')
                            .attr('title', 'This custom-option mapping has been manually verified.');
                    } else {
                        if (verifiedValue !== 'review required') {
                            $verified.text('Review required');
                        }
                        $verified
                            .addClass('walmart-verification-review')
                            .attr('title', 'Verify this custom-option mapping before enabling synchronization.');
                    }
                }

                if (columnIndexes['sync enabled'] === undefined) {
                    return;
                }

                $syncEnabled = $cells.eq(columnIndexes['sync enabled']);
                syncValue = normalizeLabel($syncEnabled.text());
                $syncEnabled.removeClass('walmart-sync-on walmart-sync-off');

                if (syncValue === 'yes') {
                    $syncEnabled
                        .addClass('walmart-sync-on')
                        .attr('title', 'Inventory synchronization is enabled for this Magento product.');
                } else {
                    $syncEnabled
                        .addClass('walmart-sync-off')
                        .attr('title', 'Inventory synchronization is disabled for this Magento product.');
                }
            });
        });
    }

    return function () {
        var timer;
        var observer;

        function scheduleApply() {
            window.clearTimeout(timer);
            timer = window.setTimeout(applyAttentionClasses, 20);
        }

        scheduleApply();

        /*
         * Magento loads UI listings asynchronously. Observe the page rather than
         * requiring the grid wrapper to exist when x-magento-init first runs.
         */
        if (document.body && window.MutationObserver) {
            observer = new window.MutationObserver(scheduleApply);
            observer.observe(document.body, {
                childList: true,
                subtree: true
            });
        }

        $(document).on('contentUpdated.walmartGridAttention', scheduleApply);
        $(document).on('ajaxComplete.walmartGridAttention', scheduleApply);
    };
});
