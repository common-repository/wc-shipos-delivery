<?php

namespace WCShiposDelivery;

class Autoloader {

	protected $classmap = array();

	public function __construct() {
		spl_autoload_register( array( $this, 'loadClass' ) );
	}

	public function loadClass( $class ) {
		$this->loadClassMap();
		if ( isset( $this->classmap[ $class ] ) ) {
			require_once $this->classmap[ $class ];
		}
	}

	public function loadClassMap() {
		return $this->classmap = array(
			'WCShiposDelivery\\Admin'      => plugin_dir_path( __FILE__ ) . 'class/class-admin.php',
			'WCShiposDelivery\\Ajax'       => plugin_dir_path( __FILE__ ) . 'class/class-ajax.php',
			'WCShiposDelivery\\Delivery'   => plugin_dir_path( __FILE__ ) . 'class/class-delivery.php',
			'WCShiposDelivery\\License'    => plugin_dir_path( __FILE__ ) . 'class/class-license.php',
			'WCShiposDelivery\\WebService' => plugin_dir_path( __FILE__ ) . 'class/class-webservice.php',
			'WCShiposDelivery\\Settings'   => plugin_dir_path( __FILE__ ) . 'class/class-settings.php',
			'WCShiposDelivery\\RestRoutes' => plugin_dir_path( __FILE__ ) . 'class/class-rest-routes.php',
			'WCShiposDelivery\\Utils'      => plugin_dir_path( __FILE__ ) . 'class/class-utils.php',
			// ...
		);
	}
}
