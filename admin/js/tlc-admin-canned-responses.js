jQuery(document).ready(function($) {
    'use strict';

    const container = $('#tlc-canned-responses-container');
    const optionNameBase = tlc_plugin_prefix + 'canned_responses'; // tlc_plugin_prefix needs to be localized

    // Add new response
    $('#tlc-add-canned-response').on('click', function() {
        const existingResponses = container.find('.tlc-canned-response-item');
        if (existingResponses.length >= 10) { // Max 10 responses
            alert('You can add a maximum of 10 predefined responses in this version.');
            return;
        }

        let newIndex = 0;
        if (existingResponses.length > 0) {
            // Find the highest existing index to ensure new index is unique
            existingResponses.each(function() {
                const nameAttr = $(this).find('input[type="text"]').first().attr('name');
                if (nameAttr) {
                    const matches = nameAttr.match(/\[(\d+)\]/);
                    if (matches && matches[1]) {
                        const currentIndex = parseInt(matches[1], 10);
                        if (currentIndex >= newIndex) {
                            newIndex = currentIndex + 1;
                        }
                    }
                }
            });
        }
         // If all items were removed and newIndex is still 0 from loop, but items were there before
        if (newIndex === 0 && existingResponses.length === 0 && container.data('last-index') !== undefined) {
             newIndex = container.data('last-index') + 1;
        }


        const template = $('#tlc-canned-response-template .tlc-canned-response-item').clone();

        template.find('input[type="text"]').attr('name', `${optionNameBase}[${newIndex}][shortcut]`).attr('id', `${optionNameBase}_${newIndex}_shortcut`);
        template.find('textarea').attr('name', `${optionNameBase}[${newIndex}][message]`).attr('id', `${optionNameBase}_${newIndex}_message`);
        template.find('label[for=""]').each(function(idx, el) {
            if (idx === 0) $(el).attr('for', `${optionNameBase}_${newIndex}_shortcut`);
            if (idx === 1) $(el).attr('for', `${optionNameBase}_${newIndex}_message`);
        });

        container.append(template);
        container.data('last-index', newIndex); // Store last index used
    });

    // Remove response
    container.on('click', '.tlc-remove-canned-response', function() {
        if (confirm('Are you sure you want to remove this response?')) {
            // If only one item left and its inputs are empty, just clear them instead of removing.
            if (container.find('.tlc-canned-response-item').length === 1) {
                 const item = $(this).closest('.tlc-canned-response-item');
                 item.find('input[type="text"]').val('');
                 item.find('textarea').val('');
                 // Do not remove the last item, allow it to be cleared and saved as empty (which sanitize will remove)
            } else {
                $(this).closest('.tlc-canned-response-item').remove();
            }
        }
    });

    // Initialize: if container is empty (after all items removed and saved), add one empty row.
    // This is handled by PHP side for initial load.
    // This JS is primarily for dynamic add/remove before save.
});
