
/*!
 * Zula Framework Module (shareable) Drag and Drop
 *
 * @patches submit all patches to patches@tangocms.org
 *
 * @author Alex Cartwright
 * @copyright Copyright (C) 2009, Alex Cartwright
 * @license http://www.gnu.org/licenses/old-licenses/gpl-2.0.html GNU/GPL 2
 * @package TangoCMS_Shareable
 */

 	/**
 	 * Drag and Drop init for the correct table
 	 */
	$(document).ready(
		function() {
			/**
			* Set the order of all the fields sequentially and remove
			* the select box, changing it with a hidden input box
			*/
			function set_order( element, row ) {
				element = (element == 'select') ? 'select' : 'input';
				$('table#shareable-sites .order '+element ).each(
					function(i) {
						$(this).replaceWith('<input type="text" name="'+$(this).attr('name')+'" value="'+(i+1)+'">');
						}
				);
				// Update the odd/even class of the rows
				$('table#shareable-sites tbody tr').each(
					function(i) {
						$(this).removeClass('odd even');
						$(this).addClass( (i % 2 == 0) ? 'even' : 'odd');
						}
					);
				$(row).addClass('ondrop');
			}
			$('table#shareable-sites').tableDnD({
													onDrop: set_order
													});
			set_order('select');
			$('table#shareable-sites .order').hide();
		}
	);
