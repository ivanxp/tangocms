<?php

/**
 * Zula Framework Controller Base
 * --- Provides a common base of methods that all controllers must extend
 *
 * @patches submit all patches to patches@tangocms.org
 *
 * @author Alex Cartwright
 * @author Robert Clipsham
 * @copyright Copyright (C) 2007, 2008, 2009, 2010 Alex Cartwright
 * @license http://www.gnu.org/licenses/old-licenses/lgpl-2.1.html GNU/LGPL 2.1
 * @package Zula
 */

	abstract class Zula_ControllerBase extends Zula_Base {

		/**
		 * Different output types a controller can output
		 */
		const
				_OT_GENERAL			= 1, # Default
				_OT_CONTENT_STATIC	= 2,
				_OT_CONTENT_DYNAMIC	= 4,
				_OT_CONTENT_INDEX	= 8,
				_OT_CONTENT			= 14,
				_OT_COLLECTIVE		= 16,
				_OT_CONFIG_ADD		= 32,
				_OT_CONFIG_EDIT		= 64,
				_OT_CONFIG			= 96,
				_OT_INFORMATIVE		= 128;

		/**
		 * Details about the parent module this controller belongs to
		 * @var array
		 */
		protected $moduleDetails = array();

		/**
		 * Sector that the module is loaded into (if any)
		 * @var string
		 */
		protected $sector = null;

		/**
		 * Old configuration values used, and will be restored
		 * once the controller has finished loading.
		 * @var array
		 */
		protected $oldConfig = array();

		/**
		 * Every model that has been loaded for this controller
		 * @var array
		 */
		protected $models = array();

		/**
		 * The text domain that this controller will use
		 * @var string
		 */
		protected $textDomain = '';

		/**
		 * Output type for the controller
		 * @param int
		 */
		protected $outputType = 1; # 1 Defaults to self::_OT_GENERAL

		/**
		 * Every stored page link
		 * @var array
		 */
		protected $pageLinks = array();

		/**
		 * Constructor
		 * Sets the module details, and any temp configuration values
		 * that may have been set for this module.
		 *
		 * @param array $moduleDetails
		 * @param array $config
		 * @param string $sector
		 * @return object
		 */
		public function __construct( array $moduleDetails, array $config=array(), $sector=null ) {
			$this->moduleDetails = $moduleDetails;
			$this->sector = strtoupper( $sector );
			// Store the older config, to be restored later
			if ( $this->_config->has( $moduleDetails['name'] ) ) {
				$this->oldConfig = $this->_config->get( $moduleDetails['name'] );
			}
			foreach( $config as $key=>$val ) {
				$key = $moduleDetails['name'].'/'.$key;
				if ( $this->_config->has( $key ) ) {
					$this->_config->update( $key, $val );
				} else {
					$this->_config->add( $key, $val );
				}
			}
			$path = $this->_zula->getDir( 'locale' );
			if ( $this->_zula->getState() == 'installation' ) {
				$domain = 'zula-installer';
			} else {
				$domain = _PROJECT_ID.'-'.$moduleDetails['name'];
			}
			$this->_i18n->bindTextDomain( $domain, $path );
			$this->textDomain = $this->_i18n->textDomain( $domain );
			$this->_log->message( 'Cntrlr constructed as "'.$moduleDetails['name'].'/'.$sector.'"', Log::L_DEBUG );
		}

		/**
		 * Destructor
		 * Restores the configuration values that were previously altered
		 *
		 * @return void
		 */
		public function __destruct() {
			foreach( $this->oldConfig as $key=>$val ) {
				$key = $this->getDetail( 'name' ).'/'.$key;
				if ( $this->_config->has( $key ) ) {
					$this->_config->update( $key, $val );
				}
			}
		}

		/**
		 * Loads a model for the current controllers module, or
		 * for a specified module.
		 *
		 * @param string $model
		 * @param string $module
		 * @return object
		 */
		protected function _model( $model=null, $module='' ) {
			if ( $module !== null && !trim( $module ) ) {
				$module = $this->getDetail( 'name' );
			}
			if ( $model === null ) {
				$model = $this->getDetail( 'name' );
			}
			return parent::_model( $model, $module );
		}

		/**
		 * Returns the text domain that should be used for this controller
		 *
		 * @return string
		 */
		protected function textDomain() {
			return $this->textDomain;
		}

		/**
		 * Sets the output type for the controller
		 *
		 * @param int $type
		 * @return object
		 */
		public function setOutputType( $type ) {
			$this->outputType = abs( $type );
			return $this;
		}

		/**
		 * Gets the output type
		 *
		 * @return int
		 */
		public function getOutputType() {
			return $this->outputType;
		}

		/**
		 * Will return a detail, if it exists, about the current controller
		 *
		 * @param string $detail
		 * @return string
		 */
		public function getDetail( $detail ) {
			if ( isset( $this->moduleDetails[$detail] ) ) {
				return $this->moduleDetails[ $detail ];
			} else {
				throw new Zula_DetailNoExist( $detail );
			}
		}

		/**
		 * Checks if a detail of the current loaded controller exists
		 *
		 * @param string $detail
		 * @return bool
		 */
		public function detailExists( $detail ) {
			return isset( $this->moduleDetails[ $detail ] );
		}

		/**
		 * Will return evey detail about the current loaded controller
		 *
		 * @return array
		 */
		public function getAllDetails() {
			return $this->moduleDetails;
		}

		/**
		 * Adds more entries to the page links array
		 *
		 * @param array $links
		 * @return bool
		 */
		public function setPageLinks( array $links ) {
			$this->pageLinks = array_merge( $this->pageLinks, $links );
			return true;
		}

		/**
		 * Returns all of the page links currently set
		 *
		 * @return array
		 */
		public function getPageLinks() {
			return $this->pageLinks;
		}

		/**
		 * Changes the title that will be displayed for the controller
		 * (if anywhere is set to show it). Default will be the controllers name
		 *
		 * @param string $title
		 * @return object
		 */
		public function setTitle( $title ) {
			$this->moduleDetails['title'] = (string) $title;
			return $this;
		}

		/**
		 * Gets the title that is set for this controller
		 *
		 * @return string
		 */
		public function getTitle() {
			return $this->moduleDetails['title'];
		}

		/**
		 * Checks if the controller is running in the specified sector
		 *
		 * @param string $sector
		 * @return bool
		 */
		public function inSector( $sector ) {
			return strtoupper($sector) == $this->getSector();
		}

		/**
		 * Gets the sector that the controller is running in
		 *
		 * @return string
		 */
		public function getSector() {
			return $this->sector;
		}

		/**
		 * Makes it easier to load view files by creating a new View object
		 * with the specified view file to be loaded automagically.
		 *
		 * Also adjusts the path used according to if the main Controller is
		 * using modules or not.
		 *
		 * @param string $viewFile
		 * @return object
		 */
		protected function loadView( $viewFile ) {
			$tmpView = new View( $viewFile, $this->getDetail( 'name' ) );
			return $tmpView;
		}

		/**
		 * Gets the path the current controller is in
		 *
		 * @return string
		 */
		public function getPath() {
			return $this->_zula->getDir( 'modules' ).'/'.$this->getDetail( 'name' );
		}

		/**
		 * Adds a new virtual asset to the theme, such as a JS or CSS file
		 *
		 * @param string|array $asset
		 * @return int
		 */
		protected function addAsset( $assets ) {
			if ( !is_array( $assets ) ) {
				$assets = array($assets);
			}
			$assetsAdded = 0;
			foreach( $assets as $asset ) {
				$asset = trim( $asset, '/ ' );
				$extension = strtolower( pathinfo($asset, PATHINFO_EXTENSION) );
				if ( $extension == 'js' ) {
					$this->_theme->addJsFile( $asset, true, $this->getDetail('name') );
				} else if ( $extension == 'css' ) {
					$url = $this->_router->makeUrl( 'assets/v/'.$this->getDetail('name') ).'/'.$asset;
					if ( $this->_theme->addHead( 'css', array('href' => $url) ) ) {
						++$assetsAdded;
					}
				}
			}
			return $assetsAdded;
		}

	}

?>
