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
            var $headerRow = $table.find('thead tr').filter(function () {
                return normalizeLabel($(this).text()).indexOf('walmart sku') !== -1;
            }).first();

            if (!$headerRow.length) {
                $headerRow = $table.find('thead tr').first();
            }

            $headerRow.children('th').each(function (index) {
                var $header = $(this);
                var className = columnClasses[normalizeLabel($header.text())];

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
        });
    }

    return function () {
        var timer;
        var target;
        var observer;

        function scheduleApply() {
            window.clearTimeout(timer);
            timer = window.setTimeout(applyAttentionClasses, 20);
        }

        scheduleApply();
        target = document.querySelector('.admin__data-grid-wrap');

        if (target && window.MutationObserver) {
            observer = new window.MutationObserver(scheduleApply);
            observer.observe(target, {
                childList: true,
                subtree: true
            });
        }

        $(document).on('contentUpdated.walmartGridAttention', scheduleApply);
    };
});
