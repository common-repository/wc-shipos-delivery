/**
 * Matat delivery plugin
 * Order page @ admin screen
 * Ajax requests
 */
(function ($) {
    'use strict';
    $(document).ready(function ($) {


        $(document).on("click", ".sync_pickup", function (event) {

            event.preventDefault();
            let btn = $(this);

            $.ajax({
                url: matat_delivery.ajax_url,
                type: 'POST',
                data: {
                    action: 'matat_sync_pickup_point'
                },
                success: function (response) {
                    btn.text(response)
                },
                error: function () {

                }
            })
        });


        $('.ship_status').click(function (e) {
            e.preventDefault();
            let dvsfw_ship_id = $(this).data('status');
            let license_key = $(this).data('license-key')
            $.ajax({
                url: matat_delivery.ajax_url,
                type: 'POST',
                data: {
                    action: 'matat_get_order_details',
                    dvsfw_order_id: dvsfw_ship_id,
                    matat_get_wpnonce: matat_delivery.matat_ajax_get_nonce,
                    license_key
                },
                success: function (response) {
                    var matat_response = JSON.parse(response);

                    var matat_status = matat_response.data.code;

                    var delivery_status = '';
                    if (matat_status != 1 && matat_status != 2) {
                        $(".matat_receiver_name").html(matat_response.data.receiver);
                        $(".matat_shipped_on").html(matat_response.data.shipped_on);
                    }


                    switch (matat_status) {
                        case "1":
                            delivery_status = matat_delivery.matat_status_1;
                            break;
                        case "2":
                            delivery_status = matat_delivery.matat_status_2;
                            break;
                        case "3":
                            delivery_status = matat_delivery.matat_status_3;
                            break;
                        case "4":
                            delivery_status = matat_delivery.matat_status_4;
                            break;
                        case "5":
                            delivery_status = matat_delivery.matat_status_5;
                            break;
                        case "7":
                            delivery_status = matat_delivery.matat_status_7;
                            break;
                        case "8":
                            delivery_status = matat_delivery.matat_status_8;
                            break;
                        case "9":
                            delivery_status = matat_delivery.matat_status_9;
                            break;
                        case "12":
                            delivery_status = matat_delivery.matat_status_12;
                            break;
                    }


                    $(".matat_delivery_status").html(matat_response.data.text);

                    //console.log(matat_response.Records.Record.Receiver);
                    $(".matat_receiver_name").html(matat_response.data.receiver);
                    $(".matat_shipped_on").html(matat_response.data.shipped_on);

                }
            })

        });

        /**
         * On click open new Matat delivery
         */
        $(document).on("click", ".matat-open-button", function (event) {

            event.preventDefault();
            $(event.target).prop("disabled", true);
            // $(".matat-open-button").hide();
            // $(".matat-powered-by").hide();
            $("#matat_open_ship").append('<img class="matat_loader" src="' + matat_delivery.matat_ajax_loader + '" alt="shipos-loader">');
            let dvsfw_order_id = $("button.matat-open-button").attr("data-order");
            let matat_urgent = "1";
            if ($('#matat_urgent').is(":checked") === true) {
                matat_urgent = "2"
            }
            let dvsfw_return = "1";
            if ($('#dvsfw_return').is(":checked") === true) {
                dvsfw_return = "2"
            }
            let matat_collect = $('#matat_collect').val();
            if (isNaN(matat_collect)) {
                matat_collect = 'NO';
            }
            let matat_motor = $('#matat_motor').val();
            let dvsfw_packages = $('#dvsfw_packages').val();
            let dvsfw_exaction_date = $('#dvsfw_exaction_date').val();

            let dvsfw_delivey_type = $('input[name=dvsfw_delivey_type]:checked').val();

            //Remove errors
            $(".matat_error_message").remove();


            $.ajax({
                url: matat_delivery.ajax_url,
                type: 'POST',
                data: {
                    action: 'matat_open_new_order',
                    dvsfw_order_id: dvsfw_order_id,
                    matat_urgent: matat_urgent,
                    dvsfw_return: dvsfw_return,
                    matat_collect: matat_collect,
                    matat_motor: matat_motor,
                    dvsfw_packages: dvsfw_packages,
                    dvsfw_exaction_date: dvsfw_exaction_date,
                    dvsfw_delivey_type: dvsfw_delivey_type,
                    dvsfw_wpnonce: matat_delivery.matat_ajax_nonce
                },
                success: function (response) {
                    $(".matat_loader").hide();
                    $(".matat-powered-by").show();

                    if (!response.success) {
                        $(".matat-button-container-open").append('<p class="matat_error_message">' + response.data + '</p>');
                        $(event.target).prop("disabled", false);
                    } else {
                        location.reload();
                    }
                },
                error: function () {
                    $(".matat_loader").hide();
                    $(".matat-powered-by").show();
                    $("#matat_open_ship").append('<p class="matat_error_message">' + matat_delivery.matat_err_message + '</p>');
                    $(event.target).prop("disabled", false);
                },
            })
        });


        $(document).on("click", ".matat-open-button-new", function (event) {

            event.preventDefault();
            console.log(event.target)
            $(event.target).prop("disabled", true);
            $("#matat_open_ship_new").append('<img class="matat_loader" src="' + matat_delivery.matat_ajax_loader + '" alt="shipos-loader">');
            let dvsfw_order_id = $("button.matat-open-button-new").attr("data-order");

            let matat_urgent = "1";
            if ($('#matat_urgent').is(":checked") === true) {
                matat_urgent = "2"
            }
            let dvsfw_return = "1";
            if ($('#dvsfw_return_new').is(":checked") === true) {
                dvsfw_return = "2"
            }


            let matat_collect = $('#matat_collect').val();
            if (isNaN(matat_collect)) {
                matat_collect = 'NO';
            }
            let matat_motor = $('#matat_motor').val();
            let dvsfw_packages = $('#dvsfw_packages_new').val();
            let dvsfw_license = $('#dvsfw_license_new').val();

            let dvsfw_exaction_date = $('#dvsfw_exaction_date_new').val();

            let dvsfw_delivey_type = $('input[name=dvsfw_delivery_type_new]:checked').val();

            //Remove errors
            $(".matat_error_message").remove();


            $.ajax({
                url: matat_delivery.ajax_url,
                type: 'POST',
                data: {
                    action: 'matat_open_new_order',
                    dvsfw_order_id: dvsfw_order_id,
                    matat_urgent: matat_urgent,
                    dvsfw_return: dvsfw_return,
                    matat_collect: matat_collect,
                    matat_motor: matat_motor,
                    dvsfw_packages: dvsfw_packages,
                    dvsfw_license: dvsfw_license,
                    dvsfw_exaction_date: dvsfw_exaction_date,
                    dvsfw_delivey_type: dvsfw_delivey_type,
                    dvsfw_wpnonce: matat_delivery.matat_ajax_nonce
                },
                success: function (response) {
                    $(".matat_loader").hide();
                    $(".matat-powered-by-new").show();

                    if (!response.success) {
                        $(".matat-button-container-open-new").append('<p class="matat_error_message">' + response.data + '</p>');
                        $(event.target).prop("disabled", false);
                    } else {
                        location.reload();
                    }
                },
                error: function () {
                    $(".matat_loader").hide();
                    $(".matat-powered-by-new").show();
                    $("#matat_open_ship_new").append('<p class="matat_error_message">' + matat_delivery.matat_err_message + '</p>');
                    $(event.target).prop("disabled", false);
                },
            })
        });

        /**
         * On click change Matat delivery status
         */
        $(document).on("click", ".matat-cancel-ship", function (event) {
            event.preventDefault();
            // $("#matat_ship_exists").hide();
            // $(".matat-powered-by").hide();
            var container = $(this).closest('.matat-button-container');
            container.append('<img class="matat_loader" src="' + matat_delivery.matat_ajax_loader + '">');
            // var dvsfw_ship_id = $(".matat_delivery_id").text();
            var dvsfw_ship_id = $(this).data('shipping-id');
            var dvsfw_license_key = $(this).data('license-key');
            var order_id = $(this).data('order-id');


            $.ajax({
                url: matat_delivery.ajax_url,
                type: 'POST',
                data: {
                    action: 'matat_change_order_status',
                    dvsfw_ship_id: dvsfw_ship_id,
                    dvsfw_license_key: dvsfw_license_key,
                    order_id: order_id,
                    matat_change_wpnonce: matat_delivery.matat_ajax_change_nonce
                },
                success: function (response) {
                    $(".matat_loader").hide();
                    if (!response.success) {
                        let message = response.data.message || matat_delivery.matat_err_message;
                        container.closest(".matat-wrapper").append('<p class="matat_error_message">' + message + '</p>');
                    } else {
                        location.reload();
                        container.closest(".matat-wrapper").append('<p class="matat_error_message">' + matat_delivery.matat_cancel_ship_ok + '</p>');
                    }
                },
                error: function () {
                    $(".matat_loader").hide();
                    container.append('<p>' + matat_delivery.matat_err_message + '</p>');
                }
            })
        });
        /**
         * On click change Matat delivery status
         */
        $(document).on("click", ".matat-reopen-ship", function (event) {
            event.preventDefault();
            $("#matat_ship_exists").hide();
            $(".matat-powered-by").hide();
            $(".matat-wrapper").append('<img class="matat_loader" src="' + matat_delivery.matat_ajax_loader + '">');
            var dvsfw_woo_order_id = $("#matat_ship_exists").attr("data-order");
            $.ajax({
                url: matat_delivery.ajax_url,
                type: 'POST',
                data: {
                    action: 'dvsfw_reopen_ship',
                    dvsfw_woo_order_id: dvsfw_woo_order_id,
                    dvsfw_reopen_wpnonce: matat_delivery.matat_ajax_reopen_nonce
                },
                success: function (response) {
                    location.reload();
                },
                error: function () {
                    $(".matat_loader").hide();
                    $(".matat-powered-by").show();
                    $(".matat-wrapper").append('<p>' + matat_delivery.matat_err_message + '</p>');
                }
            })
        });
        /**
         * Put NIS mark next to the collect box
         */
        $("#matat_collect").change(function () {
            if ($(".matat_nis").length == 0) {
                $("#matat_collect").after("<span class='matat_nis'>&#8362</span>");
            }

        });


        $('.matat_shop_order_delivery').click(function (e) {

            e.preventDefault();
            var dvsfw_order_id = $(this).closest('button.matat_shop_order_delivery').attr("data-order");


            var matat_urgent = "1";
            var dvsfw_return = "1";
            var matat_collect =
                'NO';

            var $thisCol = $('.matat-table-deliv-not.order-' + dvsfw_order_id);
            var lnonc = $('button.matat_shop_order_delivery').attr("data-nonce");


            $thisCol.append('<img width="70px" class="matat_loader" src="' + matat_delivery.matat_ajax_loader + '">');


            var matat_motor = '1';
            var dvsfw_packages = '1';

            var today = new Date();
            var dd = String(today.getDate()).padStart(2, '0');
            var mm = String(today.getMonth() + 1).padStart(2, '0'); //January is 0!
            var yyyy = today.getFullYear();

            today = yyyy + '-' + mm + '-' + dd;
            var dvsfw_exaction_date = today;

            var dvsfw_delivey_type = '1';


            $.ajax({
                url: matat_delivery.ajax_url,
                type: 'POST',
                data: {
                    action: 'matat_open_new_order',
                    dvsfw_order_id: dvsfw_order_id,
                    matat_urgent: matat_urgent,
                    dvsfw_return: dvsfw_return,
                    matat_collect: matat_collect,
                    matat_motor: matat_motor,
                    dvsfw_packages: dvsfw_packages,
                    dvsfw_exaction_date: dvsfw_exaction_date,
                    dvsfw_delivey_type: dvsfw_delivey_type,
                    dvsfw_wpnonce: matat_delivery.matat_ajax_nonce
                },
                success: function (response) {
                    console.log(response);
                    $(".matat_loader").hide();
                    $(".matat-powered-by").show();

                    if (response.success) {
                        $thisCol.text('').html('<div class="matat-table-deliv-num">\n' +
                            ' מספר שליחות: <span>' +
                            response.data + '</span></div><a class="matat-button matat-print-button" target="_blank" data-order=' + dvsfw_order_id +
                            ' href="post.php?matat_pdf=create&amp;matat_label_wpnonce=' + lnonc + '&amp;order_id=' + dvsfw_order_id + '">' +
                            'הדפס תוית</a>' + '<br><span style="color: #FF0006;margin-left: 10px">' + response.company + '</span>');
                    } else {
                        $thisCol.append('<div style="color:red">' + response.data + '</div>')
                    }

                },
                error: function () {
                    $(".matat_loader").hide();
                    $(".matat-powered-by").show();
                    $("#matat_open_ship").append('<p>' + matat_delivery.matat_err_message + '</p>');
                }
            })
        });


        $('.matat_shop_order_delivery_new').click(function (e) {

            e.preventDefault();
            var dvsfw_order_id = $(this).closest('button.matat_shop_order_delivery_new').attr("data-order");

            var dvsfw_license = $(this).closest('button.matat_shop_order_delivery_new').attr("data-license");


            var matat_urgent = "1";
            var dvsfw_return = "1";
            var matat_collect =
                'NO';

            var $thisCol = $('.matat-table-deliv-not-new.order-' + dvsfw_order_id);
            var lnonc = $('button.matat_shop_order_delivery_new').attr("data-nonce");


            $thisCol.append('<img width="70px" class="matat_loader" src="' + matat_delivery.matat_ajax_loader + '">');


            var matat_motor = '1';
            var dvsfw_packages = '1';

            var today = new Date();
            var dd = String(today.getDate()).padStart(2, '0');
            var mm = String(today.getMonth() + 1).padStart(2, '0'); //January is 0!
            var yyyy = today.getFullYear();

            today = yyyy + '-' + mm + '-' + dd;
            var dvsfw_exaction_date = today;

            var dvsfw_delivey_type = '1';


            $.ajax({
                url: matat_delivery.ajax_url,
                type: 'POST',
                data: {
                    action: 'matat_open_new_order',
                    dvsfw_order_id: dvsfw_order_id,
                    matat_urgent: matat_urgent,
                    dvsfw_return: dvsfw_return,
                    matat_collect: matat_collect,
                    matat_motor: matat_motor,
                    dvsfw_packages: dvsfw_packages,
                    dvsfw_license: dvsfw_license,
                    dvsfw_exaction_date: dvsfw_exaction_date,
                    dvsfw_delivey_type: dvsfw_delivey_type,
                    dvsfw_wpnonce: matat_delivery.matat_ajax_nonce
                },
                success: function (response) {
                    console.log(response);
                    $(".matat_loader").hide();
                    $(".matat-powered-by").show();

                    if (response.success) {
                        $thisCol.text('').html('<div class="matat-table-deliv-num-new">\n' +
                            ' מספר שליחות: <span>' +
                            response.data + '</span></div><a class="matat-button matat-print-button-new" target="_blank" data-order=' + dvsfw_order_id +
                            ' href="post.php?matat_pdf=create&amp;matat_label_wpnonce=' + lnonc + '&amp;order_id=' + dvsfw_order_id + '&amp;ship_id=' + response.shipping_id + '">' +
                            'הדפס תוית</a>' + '<br><span style="color: #FF0006;margin-left: 10px">' + response.company + '</span>');
                    } else {
                        $thisCol.append('<div style="color:red">' + response.data + '</div>')
                    }

                },
                error: function () {
                    $(".matat_loader").hide();
                    $(".matat-powered-by").show();
                    $("#matat_open_ship").append('<p>' + matat_delivery.matat_err_message + '</p>');
                }
            })
        });

        $(document).on('submit', '#posts-filter', function (event) {
            var selected_value = $('#bulk-action-selector-top').val();

            if (selected_value == 'cargo_bulk') {
                $('#doaction').prop('disabled', true);
            } else {
                $('#doaction').prop('disabled', false);
            }
        });

        $(document).on('change', '[name="dvsfw_automatic"]', function (event) {
            var selected_value = $(this).val();

            if (selected_value == 'yes') {
                $('.auto_delivery').removeClass('hidden');
            } else {
                $('.auto_delivery').addClass('hidden');
            }
        });

        function showOrHidePickupSettings() {
            let selected_value = $('[name="dvsfw_is_pickup"]').prop('checked');
            let google_maps_api_key = $('[name="dvsfw_google_maps_api_key"]').closest('tr');
            let display_preference = $('[name="dvsfw_pickup_point_display_preference"]').closest('tr');
            let display_options = $('[name="dvsfw_pickup_point_default_display"]').closest('tr');
            if (selected_value) {
                google_maps_api_key.show();
                display_preference.show();
                display_options.show();

                showOrHideDisplayOptions();

                $(document).on('change', '[name="dvsfw_pickup_point_display_preference"]', function (event) {
                    showOrHideDisplayOptions();
                });

            } else {
                google_maps_api_key.hide();
                display_preference.hide();
                display_options.hide();
            }
        }

        showOrHidePickupSettings();

        $(document).on('change', '[name="dvsfw_is_pickup"]', function (event) {
            showOrHidePickupSettings();
        });

        function showOrHideDisplayOptions() {
            let selected_value = $('[name="dvsfw_pickup_point_display_preference"]').val();
            let display_options = $('[name="dvsfw_pickup_point_default_display"]').closest('tr');

            if (selected_value === 'both') {
                display_options.show();
            } else {
                display_options.hide();
            }
        }

        $(document).on('click', '#doaction', function (e) {
            if (typeof pagenow !== 'undefined' && pagenow === 'edit-shop_order') {
                let selected = $('#bulk-action-selector-top').val();
                if (selected === 'shipos_bulk') {
                    e.preventDefault()
                    let form = $("form#posts-filter");
                    let form_data = (form.serializeArray());
                    let order_ids = form_data
                        .filter(e => e.name === 'post[]')
                        .map(e => e.value)

                    if (!order_ids.length) {
                        alert('Please select some orders');
                        return;
                    }

                    let params = new URLSearchParams();
                    params.append('page', 'deliver-via-shipos');
                    order_ids.forEach(order_id => {
                        params.append('dvsfw_select_ids[]', order_id);
                    })
                    params.append('dvsfw_action', 'bulk_ship');

                    let url = matat_delivery.admin_url + '?' + params.toString();

                    window.open(url, '_blank')
                }
            }
        })


    })
})(jQuery);


