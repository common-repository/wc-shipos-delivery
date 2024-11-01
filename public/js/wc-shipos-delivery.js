(function ($) {
    'use strict';
    $(document).ready(function () {
        calcWindowHeight();

        jQuery(document).on('click', '.shipos_popup_close', function (event) {
            event.preventDefault();
            jQuery(this).closest('.shipos-pickup-popup').removeClass('popup_opened');
        });

        jQuery(document).on('click', '.shipos_popup_open', function (event) {
            event.preventDefault();
            jQuery('.shipos-pickup-popup').addClass('popup_opened');
            setTimeout(function () {
                jQuery('#shipos_search_input').focus();
            }, 251);
        });

        // Make chosen delivery spot read only
        jQuery(document).on('updated_checkout', function () {
            jQuery('#shipos_delivery').prop('readonly', 'readonly');
        });

        // Hide shipos shipping options if not checked
        jQuery(document).on('updated_checkout', function () {
            let shipos_radio_option = jQuery('input[type=radio][id*=shipping_method][id*=shipos_delivery].shipping_method');
            if (!shipos_radio_option.prop('checked')) {
                jQuery('#shipos_pickup_checkout').css('display', 'none');
            } else {
                jQuery('#shipos_pickup_checkout').css('display', 'initial');
            }
        });
    });
})(jQuery);

window.addEventListener('resize', () => {
    calcWindowHeight();
});

function calcWindowHeight() {
    var root = document.documentElement;
    root.style.setProperty('--window-height', window.innerHeight + 'px');
}

function initShiposLocations() {
    window.shipos_vue = new Vue({
        el: '#shipos-pickup-popup',
        data() {
            return {
                searchInput: "",
                locations: [],
                filteredLocations: [],
                pickedLocation: null,
                showAutoCompleteOptions: false,
                autocompleteWidth: 'auto',
                loading: false,
                nearbyLocations: [],
                activeTab: 'manual', // TODO: Set from options,
                map: {
                    searchInput: "kathmandu",
                    infoWindow: null,
                    pickedLocation: null,
                    markers: [],
                    activeMarker: null,
                    markerIcon: null,
                    markerIconActive: null,
                    defaultLat: 32.08603595487298,
                    defaultLng: 34.7804227115141,
                }
            }
        },
        computed: {
            cities() {
                let cities = this.locations
                    .map((e) => e.city)
                    .filter(e => e.includes(this.searchInput))

                return [...new Set(cities)]
            },
            showMap() {
                return ['map', 'both'].includes(dvsfw_pickup_checkout_js.pickup_preference) && (typeof dvsfw_pickup_checkout_js !== 'undefined')
            },
            showBoth() {
                return dvsfw_pickup_checkout_js.pickup_preference === 'both' && (typeof dvsfw_pickup_checkout_js !== 'undefined')
            },
        },
        async mounted() {
            this.setActiveTab();
            await new Promise(resolve => setTimeout(resolve, 200));
            if (window.shipos_vue && !window.shipos_vue.locations.length)
                await this.getPickupLocations();

            // Hide autocomplete when clicked outside search input or autocomplete options
            document.addEventListener('click', (e) => {
                if (e.target.classList.contains('map_tab_item')) {
                    document.getElementById('shipos-pickup-popup').classList.add('map_tab_shown');
                } else if (e.target.classList.contains('manual_tab_item')) {
                    document.getElementById('shipos-pickup-popup').classList.remove('map_tab_shown');
                }
                const searchInput = document.querySelector("#shipos_search_input")
                const autocompleteOptions = document.querySelector(".shipos_location_autocomplete")
                const outsideClick = !searchInput?.contains(e.target) && !autocompleteOptions?.contains(e.target);
                if (outsideClick)
                    this.showAutoCompleteOptions = false
            })

            if (this.showMap) {
                this.initGoogleMap();
            }
        },
        methods: {
            setActiveTab() {
                if (this.showBoth) {
                    this.activeTab = dvsfw_pickup_checkout_js?.default_pickup_preference || 'map'
                    if (this.activeTab == 'map') document.getElementById('shipos-pickup-popup').classList.add('map_tab_shown')
                } else if (this.showMap) {
                    this.activeTab = 'map'
                } else {
                    this.activeTab = 'manual'
                }
            },
            setAutocompleteWidth() {
                let searchInput = this.$refs.searchInput;
                this.autocompleteWidth = searchInput?.offsetWidth + 'px' || 'auto';
            },
            handleAutoCompleteClick(city) {
                this.searchInput = city;
                this.showAutoCompleteOptions = false;
            },
            async getFilteredLocations() {
                this.loading = true;
                this.filteredLocations = [];
                await new Promise(resolve => setTimeout(resolve, 1000));

                this.filteredLocations = this.locations
                    .filter(e => e.city.includes(this.searchInput));

                if (!this.filteredLocations.length) {
                    await this.getNearestLocations(this.searchInput);
                }

                this.loading = false;
                this.clearSelectedLocation();
            },
            async getPickupLocations() {
                await jQuery.ajax({
                    url: dvsfw_pickup_checkout_js.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'dvsfw_get_pickup_locations',
                        nonce: dvsfw_pickup_checkout_js.nonce,
                    },
                    success: function (response) {
                        window.shipos_vue.locations = response.data.spots.spot_detail;
                    },
                    error: function (error) {
                        console.log({error})
                    }
                })
            },
            async getNearestLocations(location) {
                await jQuery.ajax({
                    url: dvsfw_pickup_checkout_js.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'dvsfw_get_coordinates',
                        nonce: dvsfw_pickup_checkout_js.nonce,
                        location
                    },
                    success: (response) => {
                        if (response.success) {
                            const {latitude, longitude} = response.data.data;
                            const locations = window.shipos_vue.locations || [];
                            locations.forEach((location) => {
                                location.distance = this.getDistance(latitude, location.latitude, longitude, location.longitude);
                            })
                            window.shipos_vue.filteredLocations = locations.filter((location) => location.distance <= 8);
                        }
                    },
                    error: function (error) {
                        console.log({error})
                    }
                })
            },
            setSelectedLocation(location) {
                if (location) {
                    let fields = document.querySelectorAll('input#shipos_delivery');
                    let field_ids = document.querySelectorAll('input#shipos_delivery_id');
                    fields.forEach((field) => {
                        field.value = location.name + ' ' + location.street + ' ' + location.house + ' ' + location.city;
                    })
                    field_ids.forEach((field_id) => {
                        field_id.value = location.n_code;
                    })

                    document.querySelector('.shipos_popup_close').click();
                }
            },
            clearSelectedLocation() {
                this.pickedLocation = null;
                this.map.pickedLocation = null;
                if (this.map.infoWindow)
                    this.map.infoWindow.close();
                let fields = document.querySelectorAll('input#shipos_delivery');
                let field_ids = document.querySelectorAll('input#shipos_delivery_id');
                fields.forEach(field => {
                    field.value = null;

                })
                field_ids.forEach(field_id => {
                    field_id.value = null;
                })

                if (this.activeMarker)
                    this.activeMarker.setIcon(this.map.markerIcon);

            },
            getDistance(lat1, lat2, lon1, lon2) {
                // The math module contains a function
                // named toRadians which converts from
                // degrees to radians.
                lon1 = lon1 * Math.PI / 180;
                lon2 = lon2 * Math.PI / 180;
                lat1 = lat1 * Math.PI / 180;
                lat2 = lat2 * Math.PI / 180;

                // Haversine formula
                let dlon = lon2 - lon1;
                let dlat = lat2 - lat1;
                let a = Math.pow(Math.sin(dlat / 2), 2)
                    + Math.cos(lat1) * Math.cos(lat2)
                    * Math.pow(Math.sin(dlon / 2), 2);

                let c = 2 * Math.asin(Math.sqrt(a));

                // Radius of earth in kilometers. Use 3956
                // for miles and 6371 for km
                let r = 3956;

                // calculate the result
                return parseFloat((c * r).toFixed(2));
            },
            initGoogleMap() {
                // Create the script tag, set the appropriate attributes
                const api_key = dvsfw_pickup_checkout_js.google_maps_api_key;
                const script = document.createElement('script');
                script.src = `https://maps.googleapis.com/maps/api/js?key=${api_key}&callback=initShiposGoogleMaps&libraries=places`;
                script.async = true;

                // Attach your callback function to the `window` object
                window.initShiposGoogleMaps = () => {
                    window.shiposGoogleMap = new google.maps.Map(document.getElementById("shipos_map"), {
                        zoom: 14,
                        center: {lat: this.map.defaultLat, lng: this.map.defaultLng},
                    });
                };

                // Append the 'script' element to 'head'
                document.head.appendChild(script);

                // Initialize maps autocomplete for search input after script has loaded
                script.addEventListener('load', () => {
                    this.map.markerIcon = {
                        path: "M23.7 0H9.6C4.3 0 0 4.3 0 9.6v14.1c0 5.3 4.3 9.6 9.6 9.6h14.1c5.3 0 9.6-4.3 9.6-9.6V9.6C33.3 4.3 29 0 23.7 0zm5.6 23.7c0 3.1-2.5 5.6-5.6 5.6H9.6c-3.1 0-5.6-2.5-5.6-5.6V9.6C4 6.5 6.5 4 9.6 4h14.1c3.1 0 5.6 2.5 5.6 5.6v14.1zM22.3 35.1H11c-1.1 0-1.8 1.2-1.2 2.2l5.7 9.8c.6 1 1.9 1 2.5 0l5.7-9.8c.4-1-.3-2.2-1.4-2.2z",
                        fillColor: "#E84522",
                        fillOpacity: 1,
                        strokeWeight: 0,
                        rotation: 0,
                        scale: 0.6,
                        anchor: new google.maps.Point(0, 20),
                    };
                    this.map.markerIconActive = this.map.markerIcon;
                    this.createMapsAutocomplete();
                    this.createPointersInMap(this.map.defaultLat, this.map.defaultLng)
                })
            },
            createMapsAutocomplete() {
                // let input = this.$refs.mapSearchInput;
                let input = document.querySelector("#shipos_map_search_input");
                let autocomplete = new google.maps.places.Autocomplete(input, {
                    componentRestrictions: {'country': ['IL']}
                })

                autocomplete.addListener('place_changed', () => {
                    let place = autocomplete.getPlace();

                    if (!place.geometry) {
                        input.value = ""
                    } else {
                        this.createPointersInMap(place.geometry.location.lat(), place.geometry.location.lng())
                    }
                })
            },
            createPointersInMap(latitude, longitude) {
                const map = window.shiposGoogleMap;

                // Center the map to the location
                map.setCenter({
                    lat: latitude,
                    lng: longitude
                });

                // Remove all previous markers from the map
                this.map.markers.forEach(marker => {
                    marker.setMap(null);
                })
                this.map.markers = [];

                // Add distance for each location from the searched location
                const locations = window.shipos_vue.locations || [];
                locations.forEach((location) => {
                    location.distance = this.getDistance(latitude, location.latitude, longitude, location.longitude);
                })

                // Filter locations that have distance less than 8 miles from searched location
                let collectionPoints = locations.filter((location) => location.distance <= 8).map(location => {
                    return [
                        {lat: Number(location.latitude), lng: Number(location.longitude)},
                        location.name + ' ' + location.street + ' ' + location.house + ' ' + location.city,
                        location.n_code,
                    ]
                });

                // Create an info window to share between markers.
                this.map.infoWindow = new google.maps.InfoWindow();
                // Create the markers.
                collectionPoints.forEach(([position, title, code], i) => {
                    const marker = new google.maps.Marker({
                        position,
                        map,
                        title,
                        code,
                        optimized: true,
                        icon: this.map.markerIcon
                    });

                    // Add a click listener for each marker, and set up the info window.
                    marker.addListener("click", () => {
                        if (this.activeMarker)
                            this.activeMarker.setIcon(this.map.markerIcon);
                        this.map.infoWindow.close();
                        this.map.infoWindow.setContent(marker.getTitle());
                        this.map.infoWindow.open(marker.getMap(), marker);
                        this.map.pickedLocation = this.locations.find(location => Number(location.n_code) === Number(marker.code))
                        marker.setIcon(this.map.markerIconActive)
                        this.activeMarker = marker;
                    });

                    // Populate the markers array for future use
                    this.map.markers.push(marker);
                });
            }

        },
        watch: {
            showAutoCompleteOptions() {
                this.setAutocompleteWidth();
            }
        }
    })
}

jQuery(document).ready(function () {
    initShiposLocations();
});