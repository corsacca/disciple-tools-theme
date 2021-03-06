<?php
if ( !defined( 'ABSPATH' ) ) {
    exit;
} // Exit if accessed directly.


class DT_Metrics_Mapbox_Groups_Points_Map extends DT_Metrics_Chart_Base
{

    //slug and title of the top menu folder
    public $base_slug = 'groups'; // lowercase
    public $base_title;
    public $title;
    public $slug = 'mapbox_points_map'; // lowercase
    public $js_object_name = 'wp_js_object'; // This object will be loaded into the metrics.js file by the wp_localize_script.
    public $js_file_name = '/dt-metrics/common/points-map.js'; // should be full file name plus extension
    public $permissions = [ 'view_any_contacts', 'view_project_metrics' ];
    public $namespace = 'dt-metrics/groups/';

    public function __construct() {
        if ( ! DT_Mapbox_API::get_key() ) {
            return;
        }
        parent::__construct();
        if ( !$this->has_permission() ){
            return;
        }
        $this->title = __( 'Points Map', 'disciple_tools' );
        $this->base_title = __( 'Groups', 'disciple_tools' );

        $url_path = dt_get_url_path();
        if ( "metrics/$this->base_slug/$this->slug" === $url_path ) {
            add_action( 'wp_enqueue_scripts', [ $this, 'scripts' ], 99 );
        }
        add_action( 'rest_api_init', [ $this, 'add_api_routes' ] );
    }

    public function scripts() {
        DT_Mapbox_API::load_mapbox_header_scripts();
        // Map starter Script
        wp_enqueue_script( 'dt_mapbox_script',
            get_template_directory_uri() . $this->js_file_name,
            [
                'jquery',
                'lodash'
            ],
            filemtime( get_theme_file_path() . $this->js_file_name ),
            true
        );
        $group_fields = Disciple_Tools_Groups_Post_Type::instance()->get_group_field_defaults();
        wp_localize_script(
            'dt_mapbox_script', 'dt_mapbox_metrics', [
                'translations' => [
                    'title' => __( "Mapping", "disciple_tools" ),
                    'refresh_data' => __( "Refresh Cached Data", "disciple_tools" ),
                    'population' => __( "Population", "disciple_tools" ),
                    'name' => __( "Name", "disciple_tools" ),
                    'status' => __( "Status", "disciple_tools" ),
                    'status_all' => __( "Status - All", "disciple_tools" ),
                    'zoom_level' => __( "Zoom Level", "disciple_tools" ),
                    'auto_zoom' => __( "Auto Zoom", "disciple_tools" ),
                    'world' => __( "World", "disciple_tools" ),
                    'country' => __( "Country", "disciple_tools" ),
                    'state' => __( "State", "disciple_tools" ),
                    'view_record' => __( "View Record", "disciple_tools" ),
                    'assigned_to' => __( "Assigned To", "disciple_tools" ),
                ],
                'settings' => [
                    'map_key' => DT_Mapbox_API::get_key(),
                    'map_mirror' => dt_get_location_grid_mirror( true ),
                    'points_rest_url' => 'points_geojson',
                    'points_rest_base_url' => $this->namespace,
                    'menu_slug' => $this->base_slug,
                    'post_type' => 'groups',
                    'title' => $this->title,
                    'status_list' => $group_fields['group_status']['default'] ?? []
                ]
            ]
        );
    }

    public function add_api_routes() {
        register_rest_route(
            $this->namespace, 'points_geojson', [
                [
                    'methods'  => WP_REST_Server::CREATABLE,
                    'callback' => [ $this, 'points_geojson' ],
                ],
            ]
        );
    }

    public function points_geojson( WP_REST_Request $request ) {
        if ( ! $this->has_permission() ){
            return new WP_Error( __METHOD__, "Missing Permissions", [ 'status' => 400 ] );
        }

        $params = $request->get_json_params() ?? $request->get_body_params();
        if ( ! isset( $params['post_type'] ) || empty( $params['post_type'] ) ) {
            return new WP_Error( __METHOD__, "Missing Post Types", [ 'status' => 400 ] );
        }

        $status = null;
        if ( isset( $params['status'] ) && $params['status'] !== 'all' ) {
            $status = sanitize_text_field( wp_unslash( $params['status'] ) );
        }

        return self::query_groups_points_geojson( $status );
    }

    public static function query_groups_points_geojson( $status = null ) {
        global $wpdb;

        if ( $status ) {
            $results = $wpdb->get_results($wpdb->prepare( "
                SELECT lgm.label as l, p.post_title as n, lgm.post_id as pid, lgm.lng, lgm.lat, lg.admin0_grid_id as a0, lg.admin1_grid_id as a1
                FROM $wpdb->dt_location_grid_meta as lgm
                     LEFT JOIN $wpdb->posts as p ON p.ID=lgm.post_id
                     LEFT JOIN $wpdb->dt_location_grid as lg ON lg.grid_id=lgm.grid_id
                    JOIN $wpdb->postmeta as pm ON pm.post_id=lgm.post_id AND meta_key = 'group_status' AND meta_value = %s
                WHERE lgm.post_type = 'groups'
                LIMIT 40000;
                ", $status), ARRAY_A );
        } else {
            $results = $wpdb->get_results("
                SELECT lgm.label as l, p.post_title as n, lgm.post_id as pid, lgm.lng, lgm.lat, lg.admin0_grid_id as a0, lg.admin1_grid_id as a1
                FROM $wpdb->dt_location_grid_meta as lgm
                     LEFT JOIN $wpdb->posts as p ON p.ID=lgm.post_id
                     LEFT JOIN $wpdb->dt_location_grid as lg ON lg.grid_id=lgm.grid_id
                WHERE lgm.post_type = 'groups'
                LIMIT 40000;
                ", ARRAY_A );
        }

        $features = [];
        foreach ( $results as $result ) {
            $features[] = array(
                'type' => 'Feature',
                'properties' => array(
                    "l" => $result['l'],
                    "pid" => $result['pid'],
                    "n" => $result['n'],
                    "a0" => $result['a0'],
                    "a1" => $result['a1']
                ),
                'geometry' => array(
                    'type' => 'Point',
                    'coordinates' => array(
                        $result['lng'],
                        $result['lat'],
                        1
                    ),
                ),
            );
        }

        $new_data = array(
            'type' => 'FeatureCollection',
            'features' => $features,
        );

        return $new_data;
    }

}
new DT_Metrics_Mapbox_Groups_Points_Map();
