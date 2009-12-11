<?php

/**
 * Zula Framework View
 * --- A simple tag replacement engine that also allows for PHP code
 *
 * @patches submit all patches to patches@tangocms.org
 *
 * @author Alex Cartwright
 * @author Robert Clipsham
 * @copyright Copyright (C) 2007, 2008, 2009 Alex Cartwright
 * @license http://www.gnu.org/licenses/old-licenses/lgpl-2.1.html GNU/LGPL 2.1
 * @package Zula_View
 */

	class View extends Zula_LibraryBase {

		/**
		 * Path to the view file
		 * @var string
		 */
		protected $viewPath = false;

		/**
		 * The module this is for (for locale)
		 * @var string
		 */
		protected $module = null;

		/**
		 * String content to use instead of a file
		 * @var string
		 */
		protected $loadedContent = null;

		/**
		 * Assigned tags that will replace tags with different content
		 * @var array
		 */
		protected $assignedTags = array();

		/**
		 * All stored '<noparse>' text
		 * @var array
		 */
		protected $noparse = array();

		/**
		 * Holds the status of if PHP should be parsed in view files
		 * @var bool
		 */
		protected $parsePhp = true;

		/**
		 * Constructor function
		 * Attempts to load the view file and sets which module this is for
		 *
		 * @param string $viewFile
		 * @param string $module
		 */
		public function __construct( $viewFile='', $module='' ) {
			$viewFile = trim( $viewFile );
			$this->module = trim( $module );
			if ( $viewFile ) {
				if (
					strpos( $viewFile, '.' ) !== 0 && strpos( $viewFile, DIRECTORY_SEPARATOR ) !== 0
					&& !preg_match( '#^[A-Z]{1,2}:\\\\#i', $viewFile )
				) {
					/**
					 * Build an array of locations to look in for the view file
					 */
					$locations = array();
					$locations[] = $this->module ? $this->_zula->getDir( 'modules' ).'/'.$this->module.'/views' : $this->_zula->getDir( 'views' );
					if ( defined( '_THEME_NAME' ) ) {
						$subDir = $this->module ? $this->module : 'libs';
						array_unshift( $locations, $this->_zula->getDir( 'themes' ).'/'._THEME_NAME.'/views/'.$subDir );
					}
					foreach( $locations as $location ) {
						$tmpPath = $location.'/'.$viewFile;
						if ( file_exists( $tmpPath ) ) {
							$this->viewPath = $tmpPath;
							break;
						}
					}
				} else if ( file_exists( $viewFile ) ) {
					$this->viewPath = $viewFile;
				}
				if ( $this->viewPath === false ) {
					throw new View_FileNoExist( 'view file "'.$viewFile.'" does not exist or is not readable' );
				}
			}
		}

		/**
		 * Takes a string as the content to use for the View file, instead
		 * of having to use a file.
		 *
		 * Please note, any PHP within the content will *not* be executed.
		 *
		 * @param string $content
		 * @return bool
		 */
		public function loadString( $content ) {
			if ( is_string( $content ) ) {
				$this->loadedContent = $content;
				return true;
			} else {
				return false;
			}
		}

		/**
		 * Returns every tag that is assigned to the view file
		 *
		 * @return array
		 */
		public function getAssignedTags() {
			return $this->assignedTags;
		}

		/**
		 * Sets if PHP should be parsed/allowed in the view file
		 *
		 * @param bool $allow
		 * @return bool
		 */
		public function allowPhp( $allow=true ) {
			$this->parsePhp = (bool) $allow;
			return true;
		}

		/**
		 * Assigns a new tag to use that will get replaced, all array keys
		 * will get converted to lower case!
		 *
		 * @param array $tags
		 * @param bool $overwrite If a tag already exists, should it be overwriten or appended?
		 * @param bool $allowHtml should HTML be allowed in the tag value?
		 * @param bool $prepend	Prepend content to the tag, instead of append
		 * @return bool
		 */
		public function assign( array $tags, $overwrite=true, $allowHtml=false, $prepend=false ) {
			zula_array_key_case( $tags, CASE_LOWER );
			foreach( $tags as $tag=>$val ) {
				if ( $allowHtml == false ) {
					$val = $this->cleanTagValue( $val );
				}
				unset( $tags[ $tag ] );
				$tags[ $this->cleanTag( $tag ) ] = $val;
			}
			if ( empty( $this->assignedTags ) ) {
				$this->assignedTags = $tags;
				return true;
			} else if ( $overwrite == false ) {
				/**
				 * Allows tags that have been assigned more than once to be
				 * appended onto the end, instead of overwriting the old tag
				 */
				foreach( $tags as $key=>$val ) {
					if ( isset( $this->assignedTags[ $key ] ) ) {
						if ( is_array( $val ) ) {
							if ( $prepend ) {
								$this->assignedTags[ $key ] = zula_merge_recursive( $val, $this->assignedTags[ $key ] );
							} else {
								$this->assignedTags[ $key ] = zula_merge_recursive( $this->assignedTags[ $key ], $val );
							}
						} else {
							if ( $prepend ) {
								$this->assignedTags[ $key ] = $val.$this->assignedTags[ $key ];
							} else {
								$this->assignedTags[ $key ] .= $val;
							}
						}
					} else {
						$this->assignedTags[ $key ] = $val;
					}
				}
				return true;
			} else {
				$this->assignedTags = zula_merge_recursive( $this->assignedTags, $tags );
				return true;
			}
		}

		/**
		 * Assigns a new tag but do allow HTML
		 *
		 * @param array $tags
		 * @param bool $overwrite If a tag already exists, should it be overwriten or appended?
		 * @param bool $prepend	Prepend content to the tag, instead of append
		 * @return bool
		 */
		public function assignHtml( $tags, $overwrite=true, $prepend=false ) {
			return $this->assign( $tags, $overwrite, true, $prepend );
		}

		/**
		 * Cleans up a tag to make it Alphanumeric and a few others only
		 *
		 * @param string $tag
		 * @return string
		 */
		public function cleanTag( $tag ) {
			return preg_replace( '#[^A-Z0-9_\-/ ]#i', '', $tag );
		}

		/**
		 * Cleans up a tag value by recursingly removing HTML
		 *
		 * @param mixed $value
		 * @return mixed
		 */
		public function cleanTagValue( $value ) {
			if ( is_array( $value ) ) {
				foreach( $value as $key=>$val ) {
					if ( is_array( $val ) ) {
						$value[$key] = $this->cleanTagValue( $val );
					} else if ( is_string( $val ) ) {
						$value[$key] = htmlspecialchars( $val, ENT_COMPAT, 'UTF-8' );
					}
				}
				return $value;
			} else if ( is_string( $value ) ) {
				return htmlspecialchars( $value, ENT_COMPAT, 'UTF-8' );
			} else {
				return $value;
			}
		}

		/**
		 * Checks if a tag has been assigned to this view file
		 *
		 * @return bool
		 */
		public function isAssigned( $tag ) {
			$tagTokens = explode( '.', $tag );
			if ( count( $tagTokens ) <= 1 ) {
				$tmpTag = $tag;
			} else {
				// Tag is in the format of {FOO.BAR.CAR}
				$tmpTag = $tagTokens;
			}
			try {
				$this->replaceTag( $tmpTag, '' );
				return true;
			} catch ( Exception $e ) {
				return false;
			}
		}

		/**
		 * This is the actual Tag Replacement method
		 * First it assigns the default tags that need to be used and then prepars the Tags that
		 * will be used in the PHP 'Jail' class. All of the Tags for PHP use are convertd to lowercase
		 *
		 * After all PHP is run it gets the output from the class and goes about replacing the normal
		 * {TAGS} in the view file. All language tags are also found and assigned, they appear at the start
		 * of the assigned tags array
		 *
		 * @param bool $parseConfigTags		If this is set to true, values such as {%TCM_SITE_TITLE%} will get replaced
		 * @return string
		 */
		public function getOutput( $parseConfigTags=false ) {
			$this->noparse = array(); # Restore noparse array
			// Get and assign the default tags
			$defaultTags = $this->getDefaultTags();
			$this->assignHtml( array( 'plain' => $defaultTags['plain'] ) );
			unset( $defaultTags['plain'] );
			$this->assign( $defaultTags );
			if ( $this->loadedContent === null ) {
				if ( $this->parsePhp === true ) {
					// Prepare the tags for the PHP class
					$phpTags = array();
					foreach( $this->assignedTags as $tag=>$val ) {
						$phpTags[ zula_strtolower( str_replace('-', '_', $tag) ) ] = $val;
					}
					$tmpView = new View_OB( $phpTags, $this->viewPath, $this->module );
					$tmpViewContent = $tmpView->getOutput(); # Return content of the parsed PHP view file
				} else {
					$tmpViewContent = file_get_contents( $this->viewPath );
				}
			} else {
				$tmpViewContent = $this->loadedContent;
			}

			// Gather all language tags and merge them into the final tag array
			$languageTags = $this->languageTags( $tmpViewContent );
			$this->assignedTags = array_merge( $languageTags, $this->assignedTags );

			// Remove all <noparse> text
			$tmpViewContent = preg_replace_callback( '#<noparse>(.*?)</noparse>#s', array( $this, 'extractNoparse' ), $tmpViewContent );
			preg_match_all( '@{(?!%)(.*?)(?<!%)}@', $tmpViewContent, $templateTags );
			if ( !empty( $templateTags[0] ) ) {
				foreach( $templateTags[0] as $key=>$tag ) {
					if ( zula_substr( $tag, 0, 4 ) == '{L_[' ) {
						// Replace the langauge tags without splitting the tag into .. tokens?
						try {
							$tmpViewContent = $this->replaceTag( $templateTags[1][ $key ], $tmpViewContent, true );
						} catch ( View_TagNotAssigned $e ) {
							$this->_log->message( $e->getMessage(), Log::L_NOTICE );
						} catch ( View_InvalidTagValue $e ) {
							trigger_error( 'View::getOutput() view tag has invalid assigned value:'.$e->getMessage(), E_USER_WARNING );
						}
					} else {
						$tagTokens = explode( '.', $templateTags[1][ $key ] );
						if ( count( $tagTokens ) <= 1 ) {
							$tmpTag = $templateTags[1][ $key ];
						} else {
							// Tag is in the format of {FOO.BAR.CAR}
							$tmpTag = $tagTokens;
						}
						try {
							$tmpViewContent = $this->replaceTag( $tmpTag, $tmpViewContent );
						} catch ( View_TagNotAssigned $e ) {
							$this->_log->message( $e->getMessage(), Log::L_NOTICE );
						} catch ( View_InvalidTagValue $e ) {
							trigger_error( 'View::getOutput() view tag has invalid assigned value:'.$e->getMessage(), E_USER_WARNING );
						}
					}
				}
			}
			foreach( $defaultTags as $tag=>$val ) {
				$tmpViewContent = str_replace( '{'.$tag.'}', $val, $tmpViewContent );
				if ( $parseConfigTags === true ) {
					$tmpViewContent = str_replace( '{%'.$tag.'%}', $val, $tmpViewContent );
				}
			}
			// Restore noparse text
			$text = preg_replace_callback( '#<noparse></noparse>#', array( $this, 'insertNoparse' ), $tmpViewContent );
			# hook event: cntrlr_error_output
			while( $tmpText = Hooks::notify( 'view_output', $text, $this->viewPath, $this->module ) ) {
				if ( is_string( $tmpText ) ) {
					$text = $tmpText;
				}
			}
			return $text;
		}

		/**
		 * Extracts the text inbetween all of the <noparse> tags
		 * and stores it in an array for later use.
		 *
		 * @param array $matches
		 * @return string
		 */
		protected function extractNoparse( $matches ) {
			$this->noparse[] = $matches[1];
			return '<noparse></noparse>';
		}

		/**
		 * Returns the correct string that needs to be inserted
		 * back into the text.
		 *
		 * @param array $matches
		 * @return string
		 */
		protected function insertNoparse( $matches ) {
			return array_shift( $this->noparse );
		}

		/**
		 * Replaces a tag in a string, but also checks if that tag is assigned first.
		 * This function allows for an array as the tag, in the case it will then
		 * ... 'Follow' the assigned tags array down the tree until it finds the value
		 * it needs.
		 *
		 * @param string $tag
		 * @param string $content
		 * @param bool $langTag	If set to true, the tags case will not be touched
		 * @return string
		 */
		protected function replaceTag( $tag, $content, $langTag=false ) {
			if ( is_array( $tag ) ) {
				$strTag = implode( '.', $tag );
				foreach( $tag as $val ) {
					$val = zula_strtolower( $val );
					if ( !isset( $tmpTagValue ) && isset( $this->assignedTags[ $val ] ) ) {
						$tmpTagValue = &$this->assignedTags[ $val ];
					} else if ( isset( $tmpTagValue ) ) {
						if ( array_key_exists( $val, $tmpTagValue ) ) {
							$tmpTagValue = &$tmpTagValue[ $val ];
						} else {
							throw new View_TagNotAssigned( 'view tag "'.$strTag.'" could not be replaced for view "'.$this->viewPath.'" tag has no assigned value' );
						}
					} else {
						throw new View_TagNotAssigned( 'view tag "'.$strTag.'" could not be replaced for view "'.$this->viewPath.'" tag has no assigned value' );
					}
				}
				$value = $tmpTagValue;
				$tag = $strTag;
			} else {
				$strTag = ($langTag === false) ? zula_strtolower( $tag ) : $tag;
				if ( array_key_exists( $strTag, $this->assignedTags ) ) {
					$value = $this->assignedTags[ $strTag ];
				} else {
					throw new View_TagNotAssigned( 'view tag "'.$tag.'" could not be replaced for view "'.$this->viewPath.'" tag has no assigned value' );
				}
			}
			if ( is_array( $value ) ) {
				throw new View_InvalidTagValue( 'tag "'.$tag.'" wants to get replaced with an array' );
			} else {
				return str_replace( '{'.$tag.'}', $value, $content );
			}
		}

		/**
		 * Provides the Zula Framework with Language Support for View Files
		 * It searches the view file via reg-ex to find all tags that in the format
		 * of {L_[Phrase]} and fills a new array which each of the new tags and langauge string
		 * It also updates the text domain to the current module, if we have one set.
		 * then resets it back afterwards
		 *
		 * @param string $content
		 * @return array
		 */
		protected function languageTags( $content ) {
			if ( !empty( $this->module ) ) {
				// Bind the text domain and set the domain to use
				$domain = _PROJECT_ID.'-'.$this->module;
				$this->_locale->bindTextDomain( $domain, $this->_zula->getDir( 'modules' ).'/'.$this->module.'/locale' );
				$this->_locale->textDomain( $domain );
			}
			// Gather all languages tags
			preg_match_all( '#{L_\[(.*?)\](?=})#', $content, $tags);
			if ( !empty( $tags[0] ) ) {
				$languageTags = array();
				foreach( $tags[0] as $key=>$tag ) {
					$tag = trim( $tag, '{} ' );
					// The following t() is allowed, as the actual string is pulled straight from the view file
					$value = zula_htmlspecialchars( t( $tags[1][ $key ] ) );
					$languageTags[ $tag ] = $value;
				}
				// Restore the text domain back
				$this->_locale->textDomain( Locale::_DTD );
				return $languageTags;
			} else {
				return array();
			}
		}

		/**
		 * Provides some common, default tags that can be used in
		 * every view file
		 *
		 * @return array
		 */
		public function getDefaultTags() {
			if ( !$tags = $this->_cache->get( 'view_default_tags' ) ) {
				try {
					$tmpLang = explode( '.', $this->_config->get( 'locale/default' ) );
					$lang = $tmpLang[0];
				} catch ( Config_KeyNoExist $e ) {
					$lang = 'en';
				}
				$tags = array(
								# New tags to use
								'DIR_BASE'				=> _BASE_DIR,
								'DIR_ASSETS'			=> $this->_zula->getDir( 'assets', true ),
								'DIR_JAVASCRIPT'		=> $this->_zula->getDir( 'js', true ),
								'DIR_THEME'				=> $this->_zula->getDir( 'themes', true ),
								'DIR_UPLOADS'			=> $this->_zula->getDir( 'uploads', true ),
								'META_DESCRIPTION'		=> $this->_config->get( 'meta/description' ),
								'META_KEYWORDS'			=> $this->_config->get( 'meta/keywords' ),
								'SITE_SLOGAN'			=> $this->_config->get( 'config/slogan' ),
								'SITE_TITLE'			=> $this->_config->get( 'config/title' ),
								'URL_ADMIN'				=> $this->_router->makeUrl( null, null, null, 'admin' ),
								'URL_MAIN'				=> $this->_router->makeUrl( null, null, null, 'main' ),

								'LANGUAGE'				=> $lang,
							);
				// Add in the tags that will *not* have characters convereted to HTML entities
				$tags['plain'] = array(
										'SITE_TITLE'		=> $tags['SITE_TITLE'],
										'SITE_SLOGAN'		=> $tags['SITE_SLOGAN'],
										'META_KEYWORDS'		=> $tags['META_KEYWORDS'],
										'META_DESCRIPTION'	=> $tags['META_DESCRIPTION'],
										);
				$this->_cache->add( 'view_default_tags', $tags );
			}
			// Add in some tags which should not be cached
			$tags['URL_CURRENT_ST'] = $this->_router->makeUrl( '' );
			if ( Registry::has( 'theme' ) ) {
				$curTheme = Registry::get( 'theme' )->getDetail( 'name' );
				$tags['DIR_CUR_THEME'] = $tags['DIR_THEME'].'/'.$curTheme;
			}
			return $tags;
		}

	}

?>