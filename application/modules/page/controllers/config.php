<?php

/**
 * Zula Framework Module (page)
 * --- Provides a way of creating static pages which can then be displayed
 * Also allows for parent/child relationships to provide a sort of 'book'
 * with full contents/index
 *
 * @patches submit all patches to patches@tangocms.org
 *
 * @author Alex Cartwright
 * @author Robert Clipsham
 * @copyright Copyright (C) 2007, 2008, 2009 Alex Cartwright
 * @license http://www.gnu.org/licenses/old-licenses/gpl-2.0.html GNU/GPL 2
 * @package TangoCMS_Page
 */

	class Page_controller_config extends Zula_ControllerBase {

		/**
		 * How many pages to display per-page with Pagination
		 */
		const _PER_PAGE = 12;

		/**
		 * Constructor
		 *
		 * @return object
		 */
		public function __construct( $moduleDetails, $config, $sector ) {
			parent::__construct( $moduleDetails, $config, $sector );
			$this->setPageLinks( array(
										t('Manage Pages') 	=> $this->_router->makeUrl( 'page', 'config' ),
										t('Add Page')		=> $this->_router->makeUrl( 'page', 'config', 'add' ),
										));
		}

		/**
		 * Displays an overview of pages for a user to manage. Only the parent
		 * will be shown, non of the children will be.
		 *
		 * @return string
		 */
		public function indexSection() {
			$this->_locale->textDomain( $this->textDomain() );
			$this->setTitle( t('Manage Pages') );
			$this->setOutputType( self::_OT_CONFIG );
			if ( !$this->_acl->checkMulti( array('page_add', 'page_edit', 'page_delete') ) ) {
				throw new Module_NoPermission;
			}
			// Check what pagination page we are on, and get all pages
			try {
				$curPage = abs( $this->_input->get('page')-1 );
			} catch ( Input_KeyNoExist $e ) {
				$curPage = 0;
			}
			$pages = $this->_model()->getAllPages( self::_PER_PAGE, ($curPage*self::_PER_PAGE), 0 );
			$pageCount = $this->_model()->getCount();
			if ( $pageCount > 0 ) {
				$pagination = new Pagination( $pageCount, self::_PER_PAGE );
			}
			// Build and return view
			$view = $this->loadView( 'config/overview.html' );
			$view->assign( array('PAGES' => $pages) );
			$view->assignHtml( array(
									'PAGINATION'	=> isset($pagination) ? $pagination->build() : '',
									'CSRF'			=> $this->_input->createToken( true ),
									));
			// Autocomplete/suggest feature
			$this->_theme->addJsFile( 'jQuery/plugins/autocomplete.js' );
			$this->_theme->addCssFile( 'jquery.autocomplete.css' );
			$this->addAsset( 'js/autocomplete.js' );
			return $view->getOutput();
		}

		/**
		 * Autocomplete/autosuggest JSON response
		 *
		 * @return false
		 */
		public function autocompleteSection() {
			if ( !_AJAX_REQUEST ) {
				throw new Module_AjaxOnly;
			}
			header( 'Content-Type: text/javascript; charset=utf-8' );
			$searchTitle = '%'.str_replace( '%', '\%', $this->_input->get('query') ).'%';
			$pdoSt = $this->_sql->prepare( 'SELECT id, title FROM {SQL_PREFIX}mod_page WHERE title LIKE ?' );
			$pdoSt->execute( array($searchTitle) );
			// Setup the object to return
			$jsonObj = new StdClass;
			$jsonObj->query = $this->_input->get( 'query' );
			foreach( $pdoSt->fetchAll( PDO::FETCH_ASSOC ) as $row ) {
				$resource = 'page-'.$row['id'];
				if ( $this->_acl->resourceExists( $resource ) && $this->_acl->check( $resource ) ) {
					$jsonObj->suggestions[] = $row['title'];
					$jsonObj->data[] = $this->_router->makeFullUrl( 'page', 'config', 'edit', 'admin', array('id' => $row['id']) );
				}
			}
			echo json_encode( $jsonObj );
			return false;
		}

		/**
		 * Shows form and handles adding of a new paeg
		 *
		 * @return string
		 */
		public function addSection() {
			$this->_locale->textDomain( $this->textDomain() );
			$this->setTitle( t('Add Page') );
			$this->setOutputType( self::_OT_CONFIG | self::_OT_CONTENT_STATIC );
			if ( !$this->_acl->check( 'page_add' ) ) {
				throw new Module_NoPermission;
			}
			// Check if there is a parent ID for the page to be attached to
			try {
				$pid = abs( $this->_router->getArgument( 'pid' ) );
			} catch ( Router_ArgNoExist $e ) {
				try {
					$pid = abs( $this->_input->post('page/parent') );
				} catch ( Input_KeyNoExist $e ) {
					$pid = 0;
				}
			}
			if ( $pid ) {
				try {
					$parent = $this->_model()->getPage( $pid );
					// Check permission
					$resource = 'page-'.$parent['id'];
					if ( !$this->_acl->resourceExists( $resource ) || !$this->_acl->check( $resource ) ) {
						throw new Module_NoPermission;
					}
					$this->setTitle( sprintf( t('Add child page to "%s"'), $parent['title'] ) );
				} catch ( Page_NoExist $e ) {
					$this->_event->error( t('Parent page does not exist') );
					return zula_redirect( $this->_router->makeUrl( 'page', 'config' ) );
				}
			}
			// Build form and check it is all valid
			$form = $this->buildForm( $pid );
			if ( $form->hasInput() && $form->isValid() ) {
				$fd = $form->getValues( 'page' );
				if ( !isset( $fd['parent'] ) ) {
					$fd['parent'] = 0;
				}
				$page = $this->_model()->add( $fd['title'], $fd['body'], $fd['parent'] );
				// Update ACL resource
				try {
					$roles = $this->_input->post( 'acl_resources/page-' );
				} catch ( Input_KeyNoExist $e ) {
					$roles = array();
				}
				$this->_acl->allowOnly( 'page-'.$page['id'], $roles );
				$form->success( 'page/index/'.$page['clean_title'] );
				$this->_event->success( t('Added new page') );
				if ( empty( $parent ) ) {
					$url = $this->_router->makeUrl( 'page', 'config' );
				} else {
					$treePath = $this->_model()->findPath( $fd['parent'] );
					$url = $this->_router->makeUrl( 'page', 'config', 'edit', null, array('id' => $treePath[0]['id']) );
				}
				return zula_redirect( $url );
			}
			return $form->getOutput();
		}

		/**
		 * Displays the form for and handling editing of a page
		 *
		 * @return string
		 */
		public function editSection() {
			$this->_locale->textDomain( $this->textDomain() );
			$this->setTitle( t('Edit Page') );
			$this->setOutputType( self::_OT_CONFIG | self::_OT_CONTENT_STATIC );
			if ( !$this->_acl->check( 'page_edit' ) ) {
				throw new Module_NoPermission;
			}
			// Get details of the page to edit
			try {
				$page = $this->_model()->getPage( $this->_router->getArgument('id') );
				// Check permission
				$resource = 'page-'.$page['id'];
				if ( !$this->_acl->resourceExists( $resource ) || !$this->_acl->check( $resource ) ) {
					throw new Module_NoPermission;
				}
				$quickEdit = $this->_router->hasArgument( 'qe' );
				if ( $quickEdit ) {
					$this->setTitle( sprintf( t('Quick Edit Page "%s"'), $page['title'] ) );
					$this->setPageLinks( array(
											t('Switch to Full Edit')	=> $this->_router->makeUrl( 'page', 'config', 'edit', 'admin', array('id' => $page['id']) ),
											));
				} else {
					$this->setTitle( sprintf( t('Edit Page "%s"'), $page['title'] ) );
				}
				// Build the form and check it is all valid
				$form = $this->buildForm( $page['parent'], $page['id'], $page['title'], $page['body'], $quickEdit );
				$form->setContentUrl( 'page/index/'.$page['clean_title'] );
				if ( $form->hasInput() && $form->isValid() ) {
					$fd = $form->getValues( 'page' );
					if ( !isset( $fd['parent'] ) ) {
						$fd['parent'] = 0; # Default to no parent
					}
					$this->_model()->edit( $page['id'], $fd['title'], $fd['body'], $fd['parent'] );
					// Update ACL resource
					try {
						$roles = $this->_input->post( 'acl_resources/page-'.$page['id'] );
					} catch ( Input_KeyNoExist $e ) {
						$roles = array();
					}
					$this->_acl->allowOnly( 'page-'.$page['id'], $roles );
					$form->success( 'page/index/'.$page['clean_title'] );
					$this->_event->success( t('Edited page') );
					if ( $quickEdit ) {
						$url = $this->_router->makeUrl( 'page', 'index', $page['clean_title'] );
					} else if ( empty( $fd['parent'] ) ) {
						$url = $this->_router->makeUrl( 'page', 'config' );
					} else {
						$treePath = $this->_model()->findPath( $fd['parent'] );
						$url = $this->_router->makeUrl( 'page', 'config', 'edit', null, array('id' => $treePath[0]['id']) );
					}
					return zula_redirect( $url );
				}
				return $form->getOutput();
			} catch ( Router_ArgNoExist $e ) {
				$this->_event->error( t('No page selected') );
			} catch ( Page_NoExist $e ) {
				$this->_event->error( t('Page does not exist') );
			}
			return zula_redirect( $this->_router->makeUrl('page', 'config') );
		}

		/**
		 * Builds the form view that will allow users to add or edit a page.
		 *
		 * @param int $parent
		 * @param int $id
		 * @param string $title
		 * @param string $body
		 * @param bool $isQuickEdit
		 * @return object
		 */
		protected function buildForm( $parent=null, $id=null, $title=null, $body=null, $isQuickEdit=false ) {
			$parent = abs( $parent );
			$validParents = array(0, $parent);
			if ( $id === null ) {
				$op = 'add';
			} else {
				$op = 'edit';
				/**
				 * Gather all children and find out all possible parents that this page
				 * can be part of, not including sub-children of its self.
				 */
				if ( $isQuickEdit == false ) {
					$children = $this->_model()->getChildren( $id );
					if ( $parent ) {
						$treePath = $this->_model()->findPath( $id );
						$possibleParents = $this->_model()->getChildren( $treePath[0]['id'], true, array($id) );
						array_unshift( $possibleParents, $treePath[0] );
					} else {
						$possibleParents = $this->_model()->getAllPages( 0, 0, $parent );
					}
					foreach( $possibleParents as $key=>$tmpParent ) {
						if ( $tmpParent['id'] != $id ) {
							if ( !isset( $tmpParent['depth'] ) ) {
								$possibleParents[ $key ]['depth'] = 0;
							}
							$validParents[] = $tmpParent['id'];
						} else {
							unset( $possibleParents[ $key ] );
						}
					}
				}
			}
			// Build up the form
			$form = new View_Form( 'config/form_page.html', 'page', ($op == 'add') );
			$form->addElement( 'page/id', $id, 'ID', new Validator_Int, ($op == 'edit') );
			$form->addElement( 'page/title', $title, t('Title'), new Validator_Length(2, 255) );
			if ( $isQuickEdit == false ) {
				$form->addElement( 'page/parent', $parent, t('Parent'), new Validator_InArray($validParents), !empty($parent) );
			}
			$form->addElement( 'page/body', $body, t('Body'), new Validator_Length(1, 65535) );
			$form->assign( array(
								'OP'		=> $op,
								'PARENTS'	=> isset($possibleParents) ? $possibleParents : null,
								'QUICK_EDIT'=> $isQuickEdit,
								));
			$form->assignHtml( array(
									'ACL_FORM'	=> $this->_acl->buildForm( array(t('View Page') => 'page-'.$id) ),
									'CHILDREN'	=> empty($children) ? null : $this->createChildRows( $children ),
									));
			return $form;
		}

		/**
		 * Creates the table rows for the pages children recursively
		 *
		 * @param array $children
		 * @param int $depth
		 * @return array
		 */
		protected function createChildRows( $children, $depth=0, &$i=0 ) {
			$rows = null;
			$childrenCount = count( $children );
			$orderId = 1; # Keep track of the order
			foreach( $children as $child ) {
				$view = $this->loadView( 'config/child_row.html' );
				$view->assign( array(
									'CHILD'		=> $child,
									'DEPTH'		=> $depth,
									'STYLE'		=> empty($child['children']) ? zula_odd_even($i) : 'subheading',
									'PREFIX'	=> str_pad( '- ', $depth+1, '-', STR_PAD_LEFT ),
									'COUNT'		=> $childrenCount,
									'ORDERID'	=> $orderId,
									));
				++$i;
				if ( empty( $child['children'] ) ) {
					$rows .= $view->getOutput();
				} else {
					$childRow = $view->getOutput();
					$childRow .= $this->createChildRows( $child['children'], $depth+4, $i );
					$rows .= $childRow;
				}
				++$orderId;
			}
			return $rows;
		}

		/**
		 * Gets which page(s) to delete and tries to delete them
		 *
		 * @return mixed
		 */
		public function deleteSection() {
			$this->setOutputType( self::_OT_CONFIG );
			try {
				$pids = $this->_input->post( 'page_ids' );
				$method = 'post';
				$url = $this->_router->makeUrl( 'page', 'config' );
			} catch ( Input_KeyNoExist $e ) {
				try {
					$pids = $this->_router->getArgument( 'id' );
					$method = 'get';
					$url = $this->_router->makeUrl( '/' );
				} catch ( Router_ArgNoExist $e ) {
					$this->_event->error( t('No pages selected') );
					return zula_redirect( $this->_router->makeUrl('page', 'config') );
				}
			}
			if ( $this->_input->checkToken( $method ) ) {
				$this->delete( (array) $pids );
				$this->_event->success( t('Deleted selected pages') );
			} else {
				$this->_event->error( Input::csrfMsg() );
			}
			return zula_redirect( $url );
		}

		/**
		 * Bridges between deleting a page, or update the order. This is only called
		 * when deleting or ordering children, not for deleting single pages.
		 *
		 * @return mixed
		 */
		public function bridgeSection() {
			$this->_locale->textDomain( $this->textDomain() );
			$this->setOutputType( self::_OT_CONFIG );
			if ( !$this->_input->checkToken() ) {
				$this->_event->error( Input::csrfMsg() );
			} else if ( $this->_input->has( 'post', 'page_delete' ) ) {
				$this->setTitle( t('Delete Page') );
				try {
					$this->delete( $this->_input->post('page_ids') );
					$this->_event->success( t('Deleted selected pages') );
				} catch ( Input_KeyNoExist $e ) {
					$this->_event->error( t('No pages selected') );
				}
			} else if ( $this->_input->has( 'post', 'page_update_order' ) ) {
				$this->setTitle( t('Update Page Order') );
				// Update order of all of the menu items
				if ( !$this->_acl->check( 'menu_edit_item' ) ) {
					throw new Module_NoPermission;
				}
				$execData = array();
				$sqlMiddle = null;
				foreach( $this->_input->post( 'page_order' ) as $pid=>$order ) {
					try {
						$page = $this->_model()->getPage( $pid );
						$resource = 'page-'.$page['id'];
						if ( $this->_acl->resourceExists( $resource ) && $this->_acl->check( $resource ) ) {
							$execData[] = $page['id'];
							$execData[] = abs( $order );
							$sqlMiddle .= 'WHEN id = ? THEN ? ';
						}
					} catch ( Page_NoExist $e ) {
					}
				}
				if ( $sqlMiddle !== null ) {
					$pdoSt = $this->_sql->prepare( 'UPDATE {SQL_PREFIX}mod_page SET `order` = CASE '.$sqlMiddle.'ELSE `order` END' );
					$pdoSt->execute( $execData );
				}
				$this->_event->success( t('Page order updated') );
			}
			try {
				$parent = $this->_input->post( 'page_parent' );
				$url = $this->_router->makeUrl( 'page', 'config', 'edit', null, array('id' => $parent) );
			} catch ( Input_KeyNoExist $e ) {
				$url = $this->_router->makeUrl( 'page', 'config' );
			}
			return zula_redirect( $url );
		}

		/**
		 * Attempts to delete one or more pages, returns number of pages deleted
		 *
		 * @param array $pid
		 * @return int
		 */
		protected function delete( array $pid ) {
			if ( !$this->_acl->check( 'page_delete' ) ) {
				throw new Module_NoPermission;
			}
			$delCount = 0;
			foreach( $pid as $id ) {
				$resource = 'page-'.$id;
				if ( $this->_acl->resourceExists( $resource ) && $this->_acl->check( $resource ) ) {
					try {
						$delCount += $this->_model()->delete( $id );
					} catch ( Page_NoExist $e ) {
					}
				}
			}
			return $delCount;
		}

	}

?>