(function($){
    function escapeHtml(str){
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function formatPrice(val){
        if (val === undefined || val === null || val === '') return '';
        var num = parseFloat(val);
        if (isNaN(num)) return '';
        return '$' + num;
    }

    function getContainerState($container){
        var state = $container.data('hasnat-bb-selected');
        if (!state) {
            state = {};
            $container.data('hasnat-bb-selected', state);
        }
        return state;
    }

    function syncSelectionUI($container){
        var state = getContainerState($container);
        var $tbody = $container.find('.hasnat-bb-selection-table tbody');
        var $btn = $container.find('.hasnat-bb-add-selected');

        $tbody.empty();

        var keys = Object.keys(state);
        keys.sort();

        for (var i = 0; i < keys.length; i++){
            var boothId = keys[i];
            var row = state[boothId];
            var priceText = formatPrice(row.price);
            var isAdded = !!row.added;
            var $tr = $(
                '<tr>' +
                    '<td>' + escapeHtml(row.booth_id) + '</td>' +
                    '<td>' + escapeHtml(priceText) + '</td>' +
                    '<td>' +
                        '<button type="button" class="hasnat-bb-remove-selected" data-booth-id="' + escapeHtml(row.booth_id) + '" ' + (isAdded ? 'disabled="disabled"' : '') + '>×</button>' +
                    '</td>' +
                '</tr>'
            );
            $tbody.append($tr);
        }

        var anyNotAdded = false;
        for (var k = 0; k < keys.length; k++){
            if (!state[keys[k]].added) {
                anyNotAdded = true;
                break;
            }
        }

        $btn.prop('disabled', keys.length === 0 || !anyNotAdded);

        // Update booth markers styling
        $container.find('.hasnat-bb-booth').each(function(){
            var $b = $(this);
            var id = $b.data('booth-id');
            if (id && state[id]) {
                $b.addClass('is-selected');
            } else {
                $b.removeClass('is-selected');
            }
        });
    }

    function setupMapAspectRatio($container) {
        var $map = $container.find('.hasnat-bb-map');
        var bgImage = $map.css('background-image');
        
        if (bgImage && bgImage !== 'none') {
            var imageUrl = bgImage.replace(/url\(['"]?(.*?)['"]?\)/gi, '$1');
            var img = new Image();
            
            img.onload = function() {
                var aspectRatio = (this.height / this.width) * 100;
                $map.css('padding-top', aspectRatio + '%');
                
                // Trigger booth position recalculation after aspect ratio is set
                setTimeout(function() {
                    adjustBoothPositions($container);
                }, 100);
            };
            
            img.src = imageUrl;
        }
    }

    function adjustBoothPositions($container) {
        var $map = $container.find('.hasnat-bb-map');
        var $booths = $container.find('.hasnat-bb-booth');
        
        if ($booths.length === 0) return;
        
        // Get the actual displayed dimensions of the map
        var mapWidth = $map.width();
        var mapHeight = $map.height();
        
        if (mapWidth === 0 || mapHeight === 0) return;
        
        $booths.each(function() {
            var $booth = $(this);
            var originalLeft = parseFloat($booth.data('original-left') || $booth.css('left'));
            var originalTop = parseFloat($booth.data('original-top') || $booth.css('top'));
            
            // Store original values if not already stored
            if (!$booth.data('original-left')) {
                $booth.data('original-left', originalLeft);
                $booth.data('original-top', originalTop);
            }
            
            // The percentage values should now work correctly with the proper aspect ratio
            $booth.css({
                'left': originalLeft + '%',
                'top': originalTop + '%'
            });
        });
    }

    function tooltipContent($el){
        var id = $el.data('booth-id') || '';
        var type = $el.data('type') || '';
        var typeLabel = $el.data('type-label') || '';
        var price = $el.data('price');
        var typeColor = $el.data('color') || '#111';
        var adminBooked = $el.data('admin-booked');
        var cleanLabel = (typeLabel || type || '').split(' – ')[0];
        var bottomText = '';
        var bottomBg = typeColor;
        if ($el.hasClass('status-booked')) {
            if (adminBooked === 1 || adminBooked === '1' || adminBooked === true || adminBooked === 'true') {
                bottomText = 'Booked by admin';
            } else {
                bottomText = 'Booked';
            }
            bottomBg = '#000';
        } else if ($el.hasClass('status-reserved')) {
            bottomText = 'Reserved';
            bottomBg = '#999';
        } else {
            var priceText = (price !== undefined && price !== null && price !== '') ? ('$' + price) : '';
            var typeText = (cleanLabel || '').replace(/^Booth\s+/i, '');
            bottomText = typeText + (priceText ? (' - ' + priceText) : '');
        }
        return (
            '<div class="hasnat-bb-tooltip-row hasnat-bb-tooltip-row-1">Seat - ' + escapeHtml(id) + '</div>' +
            '<div class="hasnat-bb-tooltip-row hasnat-bb-tooltip-row-2" style="background:' + escapeHtml(bottomBg) + '">' + escapeHtml(bottomText) + '</div>'
        );
    }

    function positionTooltip($tip, $booth){
        var boothRect = $booth[0].getBoundingClientRect();
        var containerRect = $booth.closest('.hasnat-bb-container')[0].getBoundingClientRect();
        
        // Position relative to container
        var relativeLeft = boothRect.left - containerRect.left + boothRect.width + 8;
        var relativeTop = boothRect.top - containerRect.top;
        
        $tip.css({ left: relativeLeft, top: relativeTop });
    }

    $(document).on('mouseenter focus', '.hasnat-bb-booth', function(e){
        var $booth = $(this);
        var $tip = $booth.closest('.hasnat-bb-container').find('.hasnat-bb-tooltip');
        $tip.html(tooltipContent($booth)).attr('aria-hidden', 'false').show();
        positionTooltip($tip, $booth);
    });
    $(document).on('mousemove', '.hasnat-bb-booth', function(e){
        // Tooltip stays with booth, no need to update on mousemove
    });
    $(document).on('mouseleave blur', '.hasnat-bb-booth', function(){
        var $tip = $(this).closest('.hasnat-bb-container').find('.hasnat-bb-tooltip');
        $tip.hide().attr('aria-hidden', 'true');
    });

    $(document).on('click keypress', '.hasnat-bb-booth', function(e){
        if (e.type === 'keypress' && e.which !== 13 && e.which !== 32) return; // Enter/Space
        var $el = $(this);
        if ($el.hasClass('status-booked') || $el.hasClass('status-reserved')) return;

        var $container = $el.closest('.hasnat-bb-container');
        var state = getContainerState($container);

        var product_id = $el.data('product-id');
        var booth_id = $el.data('booth-id');
        var price = $el.data('price');
        var map_id = $container.data('map-id');

        if (!booth_id || !product_id || !map_id) return;
        if (state[booth_id] && state[booth_id].added) return;

        if (state[booth_id]) {
            delete state[booth_id];
        } else {
            state[booth_id] = {
                booth_id: booth_id,
                product_id: product_id,
                price: price,
                map_id: map_id
            };
        }

        syncSelectionUI($container);
    });

    $(document).on('click', '.hasnat-bb-remove-selected', function(e){
        e.preventDefault();
        var $btn = $(this);
        var booth_id = $btn.data('booth-id');
        var $container = $btn.closest('.hasnat-bb-container');
        var state = getContainerState($container);
        if (booth_id && state[booth_id] && !state[booth_id].added) {
            delete state[booth_id];
            syncSelectionUI($container);
        }
    });

    $(document).on('click', '.hasnat-bb-add-selected', function(e){
        e.preventDefault();
        var $btn = $(this);
        var $container = $btn.closest('.hasnat-bb-container');
        var state = getContainerState($container);
        var keys = Object.keys(state);
        if (keys.length === 0) return;

        var map_id = $container.data('map-id');
        var booths = [];
        for (var i = 0; i < keys.length; i++){
            if (state[keys[i]].added) continue;
            booths.push({
                booth_id: state[keys[i]].booth_id,
                product_id: state[keys[i]].product_id
            });
        }

        if (booths.length === 0) {
            syncSelectionUI($container);
            return;
        }

        $btn.prop('disabled', true).addClass('selecting');

        function doAddSelected(actionName){
            return $.ajax({
                url: HASNAT_BB.ajax_url,
                method: 'POST',
                dataType: 'json',
                data: {
                    action: actionName,
                    nonce: HASNAT_BB.nonce,
                    map_id: map_id,
                    booths: booths
                }
            });
        }

        function onAddSelectedSuccess(resp){
            for (var j = 0; j < booths.length; j++){
                $container
                    .find('.hasnat-bb-booth[data-booth-id="' + booths[j].booth_id + '"]')
                    .removeClass('is-selected')
                    .addClass('status-reserved');

                if (state[booths[j].booth_id]) {
                    state[booths[j].booth_id].added = true;
                }
            }
            syncSelectionUI($container);
            $(document.body).trigger('added_to_cart', [resp.data.fragments, resp.data.cart_hash, $btn]);
        }

        doAddSelected('hasnat_bb_add_selected_to_cart').done(function(resp){
            if (resp && resp.success){
                onAddSelectedSuccess(resp);
                return;
            }

            var msg = resp && resp.data && resp.data.message ? resp.data.message : '';
            alert(msg ? msg : 'Failed to add to cart');
        }).fail(function(){
            alert('Network error');
        }).always(function(){
            $btn.removeClass('selecting');
            syncSelectionUI($container);
        });
    });

    // Initialize maps when document is ready
    $(document).ready(function() {
        $('.hasnat-bb-container').each(function() {
            var $container = $(this);
            setupMapAspectRatio($container);
            syncSelectionUI($container);
        });
    });

    // Handle window resize to maintain proper booth positions
    var resizeTimer;
    $(window).on('resize', function() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function() {
            $('.hasnat-bb-container').each(function() {
                var $container = $(this);
                adjustBoothPositions($container);
            });
        }, 250);
    });

})(jQuery);
