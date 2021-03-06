<?php

/**
 * Zula Framework Image (Malipulation)
 * --- Simple Image Malipulation using GD, it can not create images
 * from scratch.
 *
 * @patches submit all patches to patches@tangocms.org
 *
 * @author Alex Cartwright
 * @copyright Copyright (C) 2009, 2010 Alex Cartwright
 * @license http://www.gnu.org/licenses/old-licenses/lgpl-2.1.html GNU/LGPL 2.1
 * @package Zula_Image
 */

	class Image extends Image_Base {

		/**
		 * Constructor
		 * Check if the given file is loaded and attempt to create resource
		 *
		 * @param string $file
		 * @return object
		 */
		public function __construct( $file ) {
			if ( !extension_loaded( 'gd' ) ) {
				throw new Image_NoGd( 'PHP Extension "gd" is not loaded' );
			} else if ( !is_file( $file ) ) {
				throw new Image_FileNoExist( $file.' does not exist or is not a readable file' );
			}
			// Check if the image would consume too much memory.
			$imageInfo = getimagesize( $file );
			if ( !isset( $imageInfo['channels'] ) ) {
				$imageInfo['channels'] = 1;
			}
			if ( isset( $imageInfo['bits'] ) ) {
				$memoryNeeded = round( ($imageInfo[0] * $imageInfo[1] * $imageInfo['bits'] * $imageInfo['channels'] / 8 + pow(2, 16)) * 1.65 );
				$memoryLimit = zula_byte_value( ini_get('memory_limit') );
				if ( (memory_get_usage() + $memoryNeeded) > $memoryLimit * pow( 1024, 2 ) ) {
					throw new Image_LoadFailed( 'image would consume more memory than current limit' );
				}
			}
			/**
			 * Create correct resource from the mime file and store other
			 * details about the image that we may need.
			 */
			$mime = zula_get_file_mime( $file );
			switch( $mime ) {
				case 'image/jpeg':
				case 'image/jpg':
					$this->resource = @imagecreatefromjpeg( $file );
					break;

				case 'image/png':
					$this->resource = @imagecreatefrompng( $file );
					break;

				case 'image/gif':
					$this->resource = @imagecreatefromgif( $file );
			}
			if ( !is_resource( $this->resource ) ) {
				throw new Image_LoadFailed( 'image is not a valid file or has invalid mime type "'.$mime.'"' );
			}
			$this->details = array_merge( pathinfo($file),
										  array(
											  'path'			=> $file,
											  'mime'			=> $mime,
											  'originalWidth'	=> imagesx( $this->resource ),
											  'originalHeight'	=> imagesy( $this->resource ),
										  )
										);
			$this->details['width'] = $this->details['originalWidth'];
			$this->details['height'] = $this->details['originalHeight'];
			if ( !is_resource( $this->resource ) ) {
				throw new Image_LoadFailed( 'failed to create image resource' );
			}
		}

		/**
		 * Returns the GD image resource
		 *
		 * @return resource|bool
		 */
		public function getResource() {
			return $this->resource;
		}

		/**
		 * Takes the provided destination and checks that it does not exist first
		 * if so, remove it - and check that directory is writable
		 *
		 * @param string $destination
		 * @return string
		 */
		protected function prepareDestination( $destination ) {
			if ( $destination == false ) {
				$destination = $this->dirname.'/'.$this->filename;
				switch( $this->mime ) {
					case 'image/jpeg':
					case 'image/jpg':
						$destination .= '.jpg';
						break;

					case '':
					case 'image/png':
						$destination .= '.png';
						break;

					case 'image/gif':
						$destination .= '.gif';
						break;

					default:
						$destination .= '.'.$this->extension;
				}
			}
			$directory = dirname( $destination );
			if ( file_exists( $destination ) ) {
				if ( !@unlink( $destination ) ) {
					throw new Image_SaveFailed( $destination.' already exists and could not be removed' );
				}
			} else if ( !zula_make_dir( $directory ) ) {
				throw new Image_SaveFailed( $directory.' directory could not be created' );
			}
			if ( !zula_is_writable( $directory ) ) {
				throw new Image_SaveFailed( $directory.' is not writable' );
			}
			return $destination;
		}

		/**
		 * Saves the current image back to a file. If no destination is
		 * provided it will use the original path.
		 *
		 * @param string $destination
		 * @param bool $destroy	Destroy the image resource as well?
		 * @return string
		 */
		public function save( $destination=null, $destroy=true ) {
			$destination = $this->prepareDestination( $destination );
			// Use correct method to save image
			switch( $this->mime ) {
				case 'image/jpeg':
				case 'image/jpg':
					$tmpImage = imagejpeg( $this->resource, $destination );
					break;

				case '':
				case 'image/png':
					imagesavealpha( $this->resource, true );
					$tmpImage = imagepng( $this->resource, $destination );
					break;

				case 'image/gif':
					$tmpImage = imagegif( $this->resource, $destination );
					break;

				default:
					throw new Image_SaveFailed( 'could not save image, unknown mime type of "'.$this->mime.'"' );
			}
			if ( $tmpImage === false ) {
				throw new Image_SaveFailed( 'could not write "'.$destination.'" please check permissions' );
			}
			if ( $destroy ) {
				imagedestroy( $this->resource );
			}
			return $destination;
		}

		/**
		 * Destroys the image resource
		 *
		 * @return bool
		 */
		public function destroy() {
			if ( is_resource( $this->resource ) ) {
				return imagedestroy( $this->resource );
			} else {
				return false;
			}
		}

	}

?>
