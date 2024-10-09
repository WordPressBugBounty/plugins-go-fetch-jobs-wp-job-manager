jQuery(document).ready(function($) {

    var MAP                = bc_framework_pointers_tour_l18n;
    var destroyed_pointers = []; // contain ids of pointers closed

    $(document).on( 'guided_tour.setup_done', function( e, data ) {
        e.stopImmediatePropagation();
        MAP.setPlugin( data ); // open first popup
    } );

    $(document).on( 'guided_tour.current_ready', function( e ) {
        e.stopImmediatePropagation();
        MAP.openPointer();
    });

    $.fn.guided_tour = function( resume ) {

        MAP.js_pointers      = {};       // contain js-parsed pointer objects
        MAP.first_pointer      = false;    // contain first pointer anchor jQuery object
        MAP.current_pointer    = false;    // contain current pointer jQuery object
        MAP.last_set_pointer   = false;    // contain last set pointer anchor jQuery object
        MAP.last_pointer       = false;    // contain last pointer jQuery object
        MAP.last_pointer_id    = false;    // contain last pointer id
        MAP.visible_pointers   = [];       // contain ids of pointers whose anchors are visible

        MAP.hasNext = function( data ) { // check if a given pointer object has valid next property
            return typeof data.next === 'string'
              && data.next !== ''
              && typeof MAP.js_pointers[data.next].data !== 'undefined'
              && typeof MAP.js_pointers[data.next].data.id === 'string';
        };

        MAP.isVisible = function( data ) { // check if a anchor of a given pointer object is visible
            return $.inArray( data.id, MAP.visible_pointers ) !== -1;
        };

        // given a pointer object, return its the anchor jQuery object if available
        // otherwise return first available, looking at next property of subsequent pointers
        MAP.getPointerData = function( data ) {

            var $target = $( data.anchor_id );

            // Reposition the pointer if the 'target_id' provided.
            if ( data.target_id ) {
                $target = $( data.target_id );
            }

            if ( $.inArray( data.id, MAP.visible_pointers ) !== -1 ) {
                return { target: $target, data: data };
            }

            $target = false;

            while( MAP.hasNext( data ) && ! MAP.isVisible( data ) ) {

                data = MAP.js_pointers[data.next].data;

                if ( MAP.isVisible( data ) ) {
                   $target = $( data.anchor_id );
                }

            }

            return MAP.isVisible( data ) ? { target: $target, data: data } : { target: false, data: false };
        };

        // take pointer data and setup pointer plugin for anchor element
        MAP.setPlugin = function( data ) {

            if ( typeof MAP.last_pointer === 'object') {
                destroyed_pointers.push( MAP.last_pointer_id );

                MAP.last_pointer.pointer('destroy');
                MAP.last_pointer = false;
            }

            MAP.current_pointer = false;

            var pointer_data = MAP.getPointerData( data );

            if ( ! pointer_data.target || ! pointer_data.data ) {
                return;
            }

            $target = pointer_data.target;
            data    = pointer_data.data;

            $pointer = $target.pointer({
                content  : data.title + data.content,
                position : { edge: data.edge, align: data.align },
                close: function() {
                    // open next pointer if it exists
                    if ( MAP.hasNext( data ) ) {

                        if ( MAP.js_pointers[data.next].data.bind ) {

                            $(document).on( MAP.js_pointers[data.next].data.bind, function() {

                                if ( undefined === MAP.js_pointers[data.next] || ! typeof MAP.js_pointers[data.next] === 'object' ) {
                                    return;
                                }

                                MAP.setPlugin( MAP.js_pointers[data.next].data );
                            });

                        } else {
                            MAP.setPlugin( MAP.js_pointers[data.next].data );
                        }

                    }
                    $.post( ajaxurl, { pointer: data.id, action: 'dismiss-wp-pointer' } );
                },

            });

            MAP.current_pointer = { pointer: $pointer, data: data, id: data.id };

            $(document).trigger( 'guided_tour.current_ready' );
        };

        // scroll the page to current pointer then open it
        MAP.openPointer = function() {

            var $pointer = MAP.current_pointer.pointer;

            if ( ! typeof $pointer === 'object' ) {
                return;
            }

            $( 'html, body' ).animate({ // scroll page to pointer
                scrollTop: $pointer.offset().top - 520
            }, 300, function() { // when scroll completes

                MAP.last_pointer    = $pointer;
                MAP.last_pointer_id = MAP.current_pointer.data.id;

                var $widget = $pointer.pointer('widget');

                $widget.addClass( MAP.class ).addClass( MAP.current_pointer.data.id );

                MAP.setNext( $widget, MAP.current_pointer.data );
                /*
                // last pointer is reached
                if ( MAP.last_set_pointer.id === MAP.current_pointer.data.id && bc_framework_pointers_tour_l18n.options ) {

                    $( '.button.' + MAP.last_set_pointer.id ).bind( "click", function() {});
                }*/

                $pointer.pointer( 'open' ); // open

            });

        };

        // if there is a next pointer set button label to "Next", to "Close" otherwise
        MAP.setNext = function( $widget, data ) {

            if ( typeof $widget === 'object' ) {

                var $buttons = $widget.find('.wp-pointer-buttons').eq(0);
                var $close   = $buttons.find('a.close').eq(0);

                $button = $close.clone(true, true).removeClass('close');

                $buttons.find('a.close').remove();
                $button.addClass('button').addClass('button-primary');

                has_next = false;

                if ( MAP.hasNext( data ) ) {
                    has_next_data = MAP.getPointerData( MAP.js_pointers[data.next].data );
                    has_next      = has_next_data.target && has_next_data.data;

                    has_next      = has_next || undefined !== MAP.js_pointers[data.next].data;
                } else {
                    destroyed_pointers.push( MAP.last_pointer_id );
                }

                var label = has_next ? MAP.next_label : MAP.close_label;

                $button.html(label).appendTo($buttons).addClass(data.id);
            }

        };

        $( MAP.pointers ).each( function( index, pointer ) { // loop pointers data

            // do nothing if pointer plugin isn't available
            if ( ! $().pointer ) return;

            // skip pointers already displayed (for when pointers needs to be resumed from last position)
            if ( $.inArray( pointer.id, destroyed_pointers ) !== -1 ) {
                return;
            }

            MAP.js_pointers[pointer.id] = { data: pointer };

            var $target = $(pointer.anchor_id);

            // Reposition the pointer if the 'target_id' provided.
            if ( pointer.target_id ) {
                $target = $(pointer.target_id);
            }

            if ( ( $target.length && $target.is(':visible') ) || pointer.bind ) { // anchor exists and is visible or contains a binded event?
                MAP.visible_pointers.push( pointer.id );

                if ( ! MAP.first_pointer ) {
                    MAP.first_pointer = pointer;
                }
            }

            MAP.last_set_pointer = pointer;

            if ( index === ( MAP.pointers.length - 1 ) && MAP.first_pointer ) {
                $(document).trigger( 'guided_tour.setup_done', MAP.first_pointer );
            }

        });

        $(document).trigger( 'guided_tour.init' );
    }

    $(document).guided_tour();

});
