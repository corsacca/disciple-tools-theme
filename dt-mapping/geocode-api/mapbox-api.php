<?php
if ( ! defined( 'ABSPATH' ) ) { exit; } // Exit if accessed directly
/**
 * DT_Mapbox_API
 *
 * @version 1.0 Initialize
 */

if ( ! function_exists( 'dt_mapbox_api' ) ) {
    function dt_mapbox_api() {
        return new DT_Mapbox_API();
    }
}

if ( ! class_exists( 'DT_Mapbox_API' ) ) {
    class DT_Mapbox_API {

        /**
         * Mapbox Endpoint
         */
        public static $mapbox_endpoint = 'https://api.mapbox.com/geocoding/v5/mapbox.places/';

        /**
         * Mapbox GL for loading in the header
         */
        public static $mapbox_gl_js = 'https://api.mapbox.com/mapbox-gl-js/v1.1.0/mapbox-gl.js';
        public static $mapbox_gl_css = 'https://api.mapbox.com/mapbox-gl-js/v1.1.0/mapbox-gl.css';
        public static $mapbox_gl_version = '1.1.0';

        /**
         * Mapbox Geocoder loaded in the body
         */
        public static $mb_geocoder_js = 'https://api.mapbox.com/mapbox-gl-js/plugins/mapbox-gl-geocoder/v4.4.0/mapbox-gl-geocoder.min.js';
        public static $mb_geocoder_css = 'https://api.mapbox.com/mapbox-gl-js/plugins/mapbox-gl-geocoder/v4.4.0/mapbox-gl-geocoder.css';
        public static $mb_geocoder_version = '4.4.0';

        /**
         * Mapbox Key options storage
         */
        public static function get_key() {
            return get_option( 'dt_mapbox_api_key' );
        }
        public static function delete_key() {
            return delete_option( 'dt_mapbox_api_key' );
        }
        public static function update_key( $key ) {
            return update_option( 'dt_mapbox_api_key', $key, true );
        }

        /**
         * Geocoder Scripts for Echo
         */
        public static function geocoder_scripts() {
            // Mapbox requires the goecoder placed in the body at the top of the map.
            // @codingStandardsIgnoreStart
            ?>
            <script src="<?php echo esc_url_raw( self::$mb_geocoder_js ) ?>"></script>
            <link rel='stylesheet' href="<?php echo esc_url_raw( self::$mb_geocoder_css ) ?>" type='text/css' />
            <?php
            // @codingStandardsIgnoreEnd
        }

        /**
         * Forward Address Lookup
         * @param $address
         * @param null $country_code
         * @return array|bool|mixed|object
         */
        public static function forward_lookup( $address, $country_code = null ) {
            $address = str_replace( ';', ' ', $address );
            $address = utf8_uri_encode( $address );

            if ( $country_code ) {
                $url = self::$mapbox_endpoint . $address . '.json?types=address&access_token=' . self::get_key();
            } else {
                $url = self::$mapbox_endpoint  . $address . '.json?country=' . $country_code . '&types=address&access_token=' . self::get_key();
            }

            /** @link https://codex.wordpress.org/Function_Reference/wp_remote_get */
            $response = wp_remote_get( esc_url_raw( $url ) );
            $data_result = wp_remote_retrieve_body( $response );

            if ( ! $data_result ) {
                return false;
            }
            return json_decode( $data_result, true );
        }

        public static function reverse_lookup( $longitude, $latitude ) {
            $url         = self::$mapbox_endpoint  . $longitude . ',' . $latitude . '.json?access_token=' . self::get_key();
            $response = wp_remote_get( esc_url_raw( $url ) );
            $data_result = wp_remote_retrieve_body( $response );

            if ( ! $data_result ) {
                return false;
            }
            return json_decode( $data_result, true );
        }

        /**
         * Returns country_code from longitude and latitude
         *
         * @param $longitude
         * @param $latitude
         *
         * @return string|bool
         */
        public static function get_country_by_coordinates( $longitude, $latitude ) {
            $country_code = false;
            if ( self::get_key() ) {
                $url         = self::$mapbox_endpoint  . $longitude . ',' . $latitude . '.json?types=country&access_token=' . self::get_key();
                $data_result = @file_get_contents( $url );
                if ( ! $data_result ) {
                    return false;
                }
                $data = json_decode( $data_result, true );

                if ( isset( $data['features'][0]['properties']['short_code'] ) ) {
                    $country_code = strtoupper( $data['features'][0]['properties']['short_code'] );
                }
            }

            return $country_code;
        }

        /**
         * Build Components
         */
        public static function location_list_url() {
            if ( file_exists( get_template_directory() . '/dt-mapping/location-grid-list-api.php' ) ) {
                return get_template_directory_uri() . '/dt-mapping/location-grid-list-api.php';
            }
            return '';
        }

        public static function load_mapbox_header_scripts() {
            // Mabox Mapping API
            wp_enqueue_script( 'mapbox-gl', self::$mapbox_gl_js, [ 'jquery' ], self::$mapbox_gl_version, false );
            wp_enqueue_style( 'mapbox-gl-css', self::$mapbox_gl_css, [], self::$mapbox_gl_version );
        }

        public static function load_header() {
            add_action( "enqueue_scripts", [ 'DT_Mapbox_API', 'load_mapbox_header_scripts' ] );
        }

        public static function load_admin_header() {
            add_action( "admin_enqueue_scripts", [ 'DT_Mapbox_API', 'load_mapbox_header_scripts' ] );
        }

        public static function is_active_mapbox_key() : bool {
            $key = self::get_key();
            $url = self::$mapbox_endpoint . 'Denver.json?access_token=' . $key;
            $response = wp_remote_get( esc_url_raw( $url ) );
            $data_result = wp_remote_retrieve_body( $response );

            return ! empty( $data_result );
        }

        /**
         * Administrative Page Metabox
         */
        public static function metabox_for_admin() {

            if ( isset( $_POST['mapbox_key'] )
                 && ( isset( $_POST['geocoding_key_nonce'] )
                      && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['geocoding_key_nonce'] ) ), 'geocoding_key' . get_current_user_id() ) ) ) {

                $key = sanitize_text_field( wp_unslash( $_POST['mapbox_key'] ) );
                if ( empty( $key ) ) {
                    self::delete_key();
                } else {
                    self::update_key( $key );
                }
            }
            $key = self::get_key();
            $hidden_key = '**************' . substr( $key, -5, 5 );

            if ( self::is_active_mapbox_key() ) {
                $status_class = 'connected';
                $message = 'Successfully connected to selected source.';
            } else {
                $status_class = 'not-connected';
                $message = 'API NOT AVAILABLE';
            }
            ?>
            <form method="post">
                <table class="widefat striped">
                    <thead>
                    <tr><th>MapBox.com</th></tr>
                    </thead>
                    <tbody>
                    <tr>
                        <td>
                            <?php wp_nonce_field( 'geocoding_key' . get_current_user_id(), 'geocoding_key_nonce' ); ?>
                            Mapbox API Token: <input type="text" class="regular-text" name="mapbox_key" value="<?php echo ( $key ) ? esc_attr( $hidden_key ) : ''; ?>" /> <button type="submit" class="button">Update</button>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <p id="reachable_source" class="<?php echo esc_attr( $status_class ) ?>">
                                <?php echo esc_html( $message ); ?>
                            </p>
                        </td>
                    </tr>
                    </tbody>
                </table>
            </form>
            <br>

            <?php if ( empty( self::get_key() ) ) : ?>
                <table class="widefat striped">
                    <thead>
                    <tr><th>MapBox.com Instructions</th></tr>
                    </thead>
                    <tbody>
                    <tr>
                        <td>
                            <ol>
                                <li>
                                    Go to <a href="https://www.mapbox.com/">MapBox.com</a>.
                                </li>
                                <li>
                                    Register for a new account (<a href="https://account.mapbox.com/auth/signup/">MapBox.com</a>)<br>
                                    <em>(email required, no credit card required)</em>
                                </li>
                                <li>
                                    Once registered, go to your account home page. (<a href="https://account.mapbox.com/">Account Page</a>)<br>
                                </li>
                                <li>
                                    Inside the section labeled "Access Tokens", either create a new token or use the default token provided. Copy this token.
                                </li>
                                <li>
                                    Paste the token into the "Mapbox API Token" field in the box above.
                                </li>
                            </ol>
                        </td>
                    </tr>
                    </tbody>
                </table>
                <br>
            <?php endif; ?>

            <?php if ( ! empty( self::get_key() ) ) : ?>
                <table class="widefat striped">
                    <thead>
                    <tr><th>Geocoding Test</th></tr>
                    </thead>
                    <tbody>

                    <tr>
                        <td>
                            <!-- Geocoder Input Section -->
                            <?php self::geocoder_scripts() ?>
                            <style>
                                .mapboxgl-ctrl-geocoder {
                                    min-width:100%;
                                }
                                #geocoder {
                                    padding-bottom: 10px;
                                }
                                #map {
                                    width:66%;
                                    height:400px;
                                    float:left;
                                }
                                #list {
                                    width:33%;
                                    float:right;
                                }
                                #selected_values {
                                    width:66%;
                                    float:left;
                                }
                                .result_box {
                                    padding: 15px 10px;
                                    border: 1px solid lightgray;
                                    margin: 5px 0 0;
                                    font-weight: bold;
                                }
                                .add-column {
                                    width:10px;
                                }
                            </style>

                            <!-- Widget -->
                            <div id='geocoder' class='geocoder'></div>
                            <div>
                                <div id='map'></div>
                                <div id="list"></div>
                            </div>
                            <div id="selected_values"></div>

                            <!-- Mapbox script -->
                            <script>
                                window.spinner = '<img class="load-spinner" src="<?php echo esc_url( get_template_directory_uri() ) . '/spinner.svg' ?>" width="20px" />'
                                mapboxgl.accessToken = '<?php echo esc_html( self::get_key() ) ?>';
                                var map = new mapboxgl.Map({
                                    container: 'map',
                                    style: 'mapbox://styles/mapbox/streets-v11',
                                    center: [-20, 30],
                                    zoom: 1
                                });

                                map.addControl(new mapboxgl.NavigationControl());

                                var geocoder = new MapboxGeocoder({
                                    accessToken: mapboxgl.accessToken,
                                    types: 'country region district postcode locality neighborhood address place', //'country region district postcode locality neighborhood address place',
                                    marker: {color: 'orange'},
                                    mapboxgl: mapboxgl
                                });

                                document.getElementById('geocoder').appendChild(geocoder.onAdd(map));

                                // After Search Result
                                geocoder.on('result', function(e) { // respond to search
                                    geocoder._removeMarker()
                                    console.log(e)
                                })


                                map.on('click', function (e) {
                                    console.log(e)
                                    jQuery('#list').empty().append(window.spinner);

                                    let lng = e.lngLat.lng
                                    let lat = e.lngLat.lat
                                    window.active_lnglat = [lng,lat]

                                    // add marker
                                    if ( window.active_marker ) {
                                        window.active_marker.remove()
                                    }
                                    window.active_marker = new mapboxgl.Marker()
                                        .setLngLat(e.lngLat )
                                        .addTo(map);
                                    console.log(active_marker)

                                    // add polygon
                                    jQuery.get('<?php echo esc_url( self::location_list_url() ) ?>',
                                        {
                                            type: 'possible_matches',
                                            longitude: lng,
                                            latitude:  lat,
                                            nonce: '<?php echo esc_html( wp_create_nonce( 'location_grid' ) ) ?>'
                                        }, null, 'json' ).done(function(data) {

                                        console.log(data)
                                        if ( data !== undefined ) {
                                            print_click_results( data )
                                        }

                                    })
                                });


                                // User Personal Geocode Control
                                let userGeocode = new mapboxgl.GeolocateControl({
                                    positionOptions: {
                                        enableHighAccuracy: true
                                    },
                                    marker: {
                                        color: 'orange'
                                    },
                                    trackUserLocation: false
                                })
                                map.addControl(userGeocode);
                                userGeocode.on('geolocate', function(e) { // respond to search
                                    console.log(e)
                                    jQuery('#list').empty().append(window.spinner);
                                    let lat = e.coords.latitude
                                    let lng = e.coords.longitude
                                    window.active_lnglat = [lng,lat]

                                    // add polygon
                                    jQuery.get('<?php echo esc_url( self::location_list_url() ) ?>',
                                        {
                                            type: 'possible_matches',
                                            longitude: lng,
                                            latitude:  lat,
                                            nonce: '<?php echo esc_html( wp_create_nonce( 'location_grid' ) ) ?>'
                                        }, null, 'json' ).done(function(data) {
                                        console.log(data)

                                        if ( data !== undefined ) {
                                            print_click_results(data)
                                        }
                                    })
                                })

                                jQuery(document).ready(function() {
                                    jQuery('input.mapboxgl-ctrl-geocoder--input').attr("placeholder", "Enter Country")
                                })


                                function print_click_results( data ) {
                                    if ( data !== undefined ) {

                                        // print click results
                                        window.MBresponse = data

                                        let print = jQuery('#list')
                                        print.empty();
                                        print.append('<strong>Click Results</strong><br><hr>')
                                        let table_body = ''
                                        jQuery.each( data, function(i,v) {
                                            let string = '<tr><td class="add-column">'
                                            string += '<button type="button" onclick="add_selection(' + v.grid_id +')">Add</button></td> '
                                            string += '<td><strong style="font-size:1.2em;">'+v.name+'</strong> <br>'
                                            if ( v.admin0_name !== v.name ) {
                                                string += v.admin0_name
                                            }
                                            if ( v.admin1_name !== null ) {
                                                string += ' > ' + v.admin1_name
                                            }
                                            if ( v.admin2_name !== null ) {
                                                string += ' > ' + v.admin2_name
                                            }
                                            if ( v.admin3_name !== null ) {
                                                string += ' > ' + v.admin3_name
                                            }
                                            if ( v.admin4_name !== null ) {
                                                string += ' > ' + v.admin4_name
                                            }
                                            if ( v.admin5_name !== null ) {
                                                string += ' > ' + v.admin5_name
                                            }
                                            string += '</td></tr>'
                                            table_body += string
                                        })
                                        print.append('<table>' + table_body + '</table><div><h2>Success!</h2></div>')
                                    }
                                }

                                function add_selection( grid_id ) {
                                    console.log(window.MBresponse[grid_id])

                                    let div = jQuery('#selected_values')
                                    let response = window.MBresponse[grid_id]

                                    if ( window.selected_locations === undefined ) {
                                        window.selected_locations = []
                                    }
                                    window.selected_locations[grid_id] = new mapboxgl.Marker()
                                        .setLngLat( [ window.active_lnglat[0], window.active_lnglat[1] ] )
                                        .addTo(map);

                                    let name = ''
                                    name += response.name
                                    if ( response.admin1_name !== undefined && response.level > '1' ) {
                                        name += ', ' + response.admin1_name
                                    }
                                    if ( response.admin0_name && response.level > '0' ) {
                                        name += ', ' + response.admin0_name
                                    }

                                    div.append('<div class="result_box" id="'+grid_id+'">' +
                                        '<span>'+name+'</span>' +
                                        '<span style="float:right;cursor:pointer;" onclick="remove_selection(\''+grid_id+'\')">X</span>' +
                                        '<input type="hidden" name="selected_grid_id['+grid_id+']" value="' + grid_id + '" />' +
                                        '<input type="hidden" name="selected_lnglat['+grid_id+']" value="' + window.active_lnglat[0] + ',' + window.active_lnglat[1] + '" />' +
                                        '</div>')

                                }

                                function remove_selection( grid_id ) {
                                    window.selected_locations[grid_id].remove()
                                    jQuery('#' + grid_id ).remove()
                                }
                            </script>
                        </td>
                    </tr>
                    </tbody>
                </table>
            <?php endif;
        }

        /**
         * @return string
         */
        public static function get_real_ip_address() {
            $ip = '';
            if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) )   //check ip from share internet
            {
                $ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_CLIENT_IP'] ) );
            } else if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) )   //to check ip is pass from proxy
            {
                $ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) );
            } else if ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
                $ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
            }

            return $ip;
        }
    }
}

