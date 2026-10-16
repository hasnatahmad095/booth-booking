(function($){
    $(function(){
        var file_frame;
        var booths = [];
        var isDragging = false;
        var currentDragging = null;
        var mapContainer = null;
        var mapImage = null;

        function normalizeHexColor(value){
            value = (value || '').toString().trim();
            if (!value) return '';
            if (value[0] !== '#') value = '#' + value;
            if (!/^#[0-9a-fA-F]{6}$/.test(value)) return '';
            return value;
        }

        // Image upload functionality (existing)
        $('#hasnat_bb_map_upload').on('click', function(e){
            e.preventDefault();
            if (file_frame) { file_frame.open(); return; }
            file_frame = wp.media.frames.file_frame = wp.media({
                title: 'Select Map Image',
                button: { text: 'Use this image' },
                multiple: false
            });
            file_frame.on('select', function(){
                var attachment = file_frame.state().get('selection').first().toJSON();
                $('#hasnat_bb_map_image_id').val(attachment.id);
                $('#hasnat-bb-map-preview').attr('src', attachment.url).show();
                $('#hasnat_bb_map_remove').show();
                initializeVisualEditor();
            });
            file_frame.open();
        });

        $('#hasnat_bb_map_remove').on('click', function(){
            $('#hasnat_bb_map_image_id').val('');
            $('#hasnat-bb-map-preview').attr('src','').hide();
            $(this).hide();
            clearVisualEditor();
        });

        // Toggle between visual and JSON editor
        $('#hasnat-bb-toggle-editor').on('click', function(){
            $('#hasnat-bb-visual-editor').toggleClass('hidden');
            var text = $('#hasnat-bb-visual-editor').hasClass('hidden') ? 'Show Visual Editor' : 'Hide Visual Editor';
            $(this).text(text);
        });

        // Initialize visual editor when page loads
        initializeVisualEditor();

        function initializeVisualEditor() {
            var imageSrc = $('#hasnat-bb-map-preview').attr('src');
            if (!imageSrc) {
                clearVisualEditor();
                return;
            }

            // Load existing booths from JSON
            loadBoothsFromJSON();
            
            // Setup map container
            setupMapContainer();
            
            // Render booths
            renderBooths();
            
            // Update booth list
            updateBoothList();
        }

        function clearVisualEditor() {
            $('#hasnat-bb-map-container').empty().addClass('hasnat-bb-no-image').text('Please upload a map image first to use the visual editor.');
            booths = [];
            updateBoothList();
        }

        function setupMapContainer() {
            var container = $('#hasnat-bb-map-container');
            container.removeClass('hasnat-bb-no-image').empty();
            
            mapContainer = container;
            var src = $('#hasnat-bb-map-preview').attr('src');
            mapImage = $('<img>').attr('src', src).addClass('hasnat-bb-map-image');
            container.append(mapImage);
            
            // Wait for image to load to get dimensions
            mapImage.on('load', function() {
                renderBooths();
            });
        }

        function loadBoothsFromJSON() {
            var jsonText = $('textarea[name="hasnat_bb_booths_json"]').val() || '[]';
            try {
                booths = JSON.parse(jsonText);
                if (!Array.isArray(booths)) {
                    booths = [];
                }
            } catch (e) {
                booths = [];
            }
        }

        function saveBoothsToJSON() {
            var jsonText = JSON.stringify(booths, null, 2);
            $('textarea[name="hasnat_bb_booths_json"]').val(jsonText);
        }

        function renderBooths() {
            if (!mapContainer || !mapImage || mapImage.length === 0) return;
            
            // Remove existing booth elements
            $('.hasnat-bb-booth-editor').remove();
            
            var containerWidth = mapContainer.width();
            var containerHeight = mapContainer.height();
            
            booths.forEach(function(booth, index) {
                if (!booth.id || booth.x === undefined || booth.x === null || booth.y === undefined || booth.y === null) return;

                var boothColor = normalizeHexColor(booth.color);
                
                var boothEl = $('<div>')
                    .addClass('hasnat-bb-booth-editor')
                    .attr('data-booth-index', index)
                    .css({
                        left: booth.x + '%',
                        top: booth.y + '%',
                        background: boothColor ? boothColor : ''
                    });
                
                var idSpan = $('<span>').addClass('booth-id').text(booth.id);
                boothEl.append(idSpan);
                
                mapContainer.append(boothEl);
                
                // Make draggable
                makeDraggable(boothEl, index);
            });
        }

        function makeDraggable(element, boothIndex) {
            var startX, startY, initialLeft, initialTop;
            
            element.on('mousedown', function(e) {
                e.preventDefault();
                isDragging = true;
                currentDragging = { element: element, index: boothIndex };
                element.addClass('dragging');
                
                startX = e.pageX;
                startY = e.pageY;
                
                var currentLeft = parseFloat(element.css('left'));
                var currentTop = parseFloat(element.css('top'));
                initialLeft = currentLeft;
                initialTop = currentTop;
                
                $(document).on('mousemove.drag', function(e) {
                    if (!isDragging) return;
                    
                    var deltaX = e.pageX - startX;
                    var deltaY = e.pageY - startY;
                    
                    var newLeft = initialLeft + deltaX;
                    var newTop = initialTop + deltaY;
                    
                    // Constrain to container
                    var containerWidth = mapContainer.width();
                    var containerHeight = mapContainer.height();
                    var elementWidth = element.width();
                    var elementHeight = element.height();
                    
                    newLeft = Math.max(0, Math.min(newLeft, containerWidth - elementWidth));
                    newTop = Math.max(0, Math.min(newTop, containerHeight - elementHeight));
                    
                    element.css({
                        left: newLeft + 'px',
                        top: newTop + 'px'
                    });
                });
                
                $(document).on('mouseup.drag', function() {
                    if (!isDragging) return;
                    
                    isDragging = false;
                    element.removeClass('dragging');
                    
                    // Convert pixel position to percentage
                    var containerWidth = mapContainer.width();
                    var containerHeight = mapContainer.height();
                    var elementWidth = element.width();
                    var elementHeight = element.height();
                    
                    var currentLeft = parseFloat(element.css('left'));
                    var currentTop = parseFloat(element.css('top'));
                    
                    // Calculate center position as percentage
                    var xPercent = ((currentLeft + elementWidth / 2) / containerWidth) * 100;
                    var yPercent = ((currentTop + elementHeight / 2) / containerHeight) * 100;
                    
                    // Update booth data
                    booths[boothIndex].x = Math.round(xPercent * 10) / 10; // Round to 1 decimal
                    booths[boothIndex].y = Math.round(yPercent * 10) / 10;
                    
                    // Update element position to percentage
                    element.css({
                        left: booths[boothIndex].x + '%',
                        top: booths[boothIndex].y + '%'
                    });
                    
                    // Save to JSON
                    saveBoothsToJSON();
                    
                    // Update booth list
                    updateBoothList();
                    
                    $(document).off('mousemove.drag mouseup.drag');
                });
            });
        }

        function updateBoothList() {
            var listContainer = $('#hasnat-bb-booth-list');
            listContainer.empty();
            
            if (booths.length === 0) {
                listContainer.append('<div class="hasnat-bb-booth-item">No booths added yet</div>');
                return;
            }
            
            booths.forEach(function(booth, index) {
                var item = $('<div>').addClass('hasnat-bb-booth-item');
                
                var info = $('<div>').addClass('hasnat-bb-booth-info');
                info.append(
                    $('<span>')
                        .addClass('hasnat-bb-booth-id-display')
                        .text((booth.id || '').toString())
                );
                info.append(
                    $('<span>')
                        .addClass('hasnat-bb-booth-position')
                        .text('X: ' + (booth.x || 0) + '%, Y: ' + (booth.y || 0) + '%')
                );
                var typeText = (booth.type_label || booth.type || 'default');
                info.append(
                    $('<span>')
                        .addClass('hasnat-bb-booth-type')
                        .text(typeText.toString())
                );
                
                var removeBtn = $('<button>').addClass('hasnat-bb-remove-booth').text('Remove').on('click', function() {
                    removeBooth(index);
                });
                
                item.append(info).append(removeBtn);
                listContainer.append(item);
            });
        }

        function removeBooth(index) {
            booths.splice(index, 1);
            saveBoothsToJSON();
            renderBooths();
            updateBoothList();
        }

        // Add new booth functionality
        $('#hasnat-bb-add-booth').on('click', function() {
            var id = $('#hasnat-bb-booth-id').val().trim();
            var type = $('#hasnat-bb-booth-type').val().trim();
            var typeLabel = $('#hasnat-bb-booth-type-label').val().trim();
            var color = normalizeHexColor($('#hasnat-bb-booth-color').val());
            var productId = $('#hasnat-bb-booth-product').val();
            var price = $('#hasnat-bb-booth-price').val();
            
            if (!id) {
                alert('Please enter a booth ID');
                return;
            }
            
            if (!productId) {
                alert('Please enter a product ID');
                return;
            }

            if (!type) {
                alert('Please enter a booth type');
                return;
            }
            
            // Check for duplicate ID
            if (booths.some(function(b) { return b.id === id; })) {
                alert('Booth ID already exists');
                return;
            }
            
            // Add new booth at center position
            var newBooth = {
                id: id,
                x: 50,
                y: 50,
                type: type,
                type_label: typeLabel,
                color: color,
                product_id: parseInt(productId),
                price: parseFloat(price) || 0,
                admin_booked: false
            };
            
            booths.push(newBooth);
            saveBoothsToJSON();
            renderBooths();
            updateBoothList();
            
            // Clear form
            $('#hasnat-bb-booth-id').val('');
            $('#hasnat-bb-booth-price').val('');
            $('#hasnat-bb-booth-type').val('');
            $('#hasnat-bb-booth-type-label').val('');
            $('#hasnat-bb-booth-color').val('');
        });
    });
})(jQuery);
