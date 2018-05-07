/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

import * as $ from 'jquery';
import 'bootstrap';
import moment = require('moment');
// import Icons = require('TYPO3/CMS/Backend/Icons');

/**
 * Module: TYPO3/CMS/Backend/InfoWindow
 * @exports TYPO3/CMS/Backend/InfoWindow
 */
class DigitalAssetManagementActions {

	static folderPartial: string = '    <div class="grid folder-action {mimetype}" data-method="getContent" ' +
		'data-parameter="{identifier}">\n' +
		'   <div class="grid-cell" >\n'+
		'      <div class="icon folder-icon {type}"></div>' +
		'   </div>\n' +
		'   <div class="info">\n' +
		'      <div class="grid-cell filename"><h5 class="card-title">{name}</h5></div>\n' +
		'   </div>\n' +
		'  </div>\n';

	static filePartial: string = '<div class="grid file {mimetype}">\n' +
		// '    <img class="card-img-top" src="PlaceholderImage" data-src="{uid}" width="180" height="120"/>\n' +
		'    <div class="preview" >'+
		'<img src="/typo3conf/ext/digital_asset_management/Resources/Public/Images/empty.png" data-src="{identifier}"></div>\n' +
		'    <div class="grid-cell" >\n'+
		'        <div class="icon icon-mimetypes-{mimetype}" /></div>\n'+
		'    <div class="info">\n' +
		'      <div class="grid-cell filename"><h5>{name}</h5></div>\n' +
		'      <div class="grid-cell filesize"><p><span class="grid-label">{lll:dam.labels.filesize}: </span>{size}</p></div>\n' +
		'      <div class="grid-cell moddate"><p><span class="grid-label">{lll:dam.labels.modified}: </span>{modification_date_formated}</p></div>\n' +
		'    </div>' +
		'  </div>\n';

	static breadcrumbPartial: string = '<span class="folder-action" data-method="getContent" ' +
		'data-parameter="{identifier}">{label}</span>';

	/**
	 *
	 */
	public static init(): void {
		let my = DigitalAssetManagementActions;
		console.log('DigitalAssetManagement.init');
		// my.renderBreadcrumb('/');
		// @todo: get filetree starting point from user settings.
		my.request('getContent', '');
		$('.digital-asset-management').on('click', '.folder-action', function(): void {
			let method = $(this).data('method');
			let parameter = $(this).data('parameter');
			console.log ( 'method: ' + method + ', par: ' + parameter);
			my.request(method, parameter);
		});
		$('.digital-asset-management').on('click', '.view-action', function(): void {
			let action = $(this).data('action');
			let parameter = $(this).data('parameter');
			console.log ( 'action: ' + action + ', par: ' + parameter);
			// Remove all other view-* classes and add the clicked class
			$('.maincontent').removeClass(function (index, className) {
				return (className.match (/(^|\s)view-\S+/g) || []).join(' ');
			}).addClass(action);
		});
	}

	/**
	 *
	 * @param err any
	 */
	public static renderError(err: any): void {
		$('.errorlog').html(err.responseText);
	}

	/**
	 *
	 * @param data object
	 */
	public static renderContent(data: any): void {
		let my = DigitalAssetManagementActions;
		if (data && data.request) {
			$('.errorlog').html(data.request + data.response);
		}
		if (data.getContent && (data.getContent.files || data.getContent.folder)) {
			// Show folders and files
			let html = '';
			for (let i = 0; i < data.getContent.folders.length; i++) {
				const folder = data.getContent.folders[i];
				// Icons.getIcon('apps-filetree-folder', 'large').done( (iconMarkup: string): void => {
				// 	$('.folder-icon').html(iconMarkup);
				// });
				// @todo: use moment.js for date-formatting?!
				// @todo: how to get the thumbnail images without viewhelper?
				folder.mimetype = 'folder';
				//folder.modification_date_formated = moment(folder.modification_date).format(TYPO3.settings.DateTimePicker.DateFormat[1] || 'YYYY-MM-DD');
				html += my.replaceTemplateVars(my.folderPartial, folder);
			}
			$('.folders').html(html);
			html = '';
			// icon mimetypes-pdf
			for (let i = 0; i < data.getContent.files.length; i++) {
				const file = data.getContent.files[i];
				// @todo: how to get the thumbnail images without viewhelper?
				// Add mimetype as two classes: image/jpeg -> image jpeg
				file.mimetype = file.mimetype.replace('/', ' ');
				file.modification_date_formated = moment.unix(file.modification_date).format(top.TYPO3.settings.DateTimePicker.DateFormat[1] || 'YYYY-MM-DD');
				html += my.replaceTemplateVars(my.filePartial, file);
			}
			$('.files').html(html);
		} else {
			// Show storage infos
		}
	}

	protected static renderBreadcrumb(data: any): void {
		let html = '';
		let my = DigitalAssetManagementActions;
		if (data.getContent && data.getContent.breadcrumbs) {
			for (let i = 0; i < data.getContent.breadcrumbs.length; i++) {
				const part = data.getContent.breadcrumbs[i];
				if (part.type === 'home') {
					part.label = TYPO3.lang['dam.labels.files'];
				} else {
					part.label = part.name;
					html += '&nbsp;&gt;&nbsp;';
				}
				// Render single breadcrumb item
				html += my.replaceTemplateVars(my.breadcrumbPartial, part);
			}
			if (html) {
				$('.breadcrumb').html(html).removeClass('empty');
			} else {
				$('.breadcrumb').html('').addClass('empty');
			}
			if (data.getContent.files.length) {
				$('.files').removeClass('empty');
			} else {
				$('.files').addClass('empty');
			}
			if (data.getContent.folders.length) {
				$('.folders').removeClass('empty');
			} else {
				$('.folders').addClass('empty');
			}
		}
	}

	/**
	 *  load thumbnail from getThumbnail
	 *  @todo: only request images which are in the viewport, and trigger this when scrolling
	 */
	protected static loadThumbs() {
		let my = DigitalAssetManagementActions;
		$('.grid.image').each(function(index, el){
			let $el = $(this).find('img');
			let src = $el.attr('data-src');
			if (src) {
				my.request('getThumbnail', src);
			}
		});
	}

	protected static renderThumb(data) {
		let my = DigitalAssetManagementActions;
		if (data.getThumbnail && data.getThumbnail.thumbnail) {
			$('.grid.image').each(function (index, el) {
				let $el = $(this).find('img');
				if (data.actionparam === $el.attr('data-src')){
					$el.attr('src', data.getThumbnail.thumbnail);
					$(this).find('.icon').addClass('small');
				}
			});
		}
	}

	/**
	 * query a json backenendroute
	 *
	 * @param {string} method
	 * @param {string} parameter
	 */
	protected static request(method: string, parameter: string): void {
		let my = DigitalAssetManagementActions;
		// @todo: why does TYPO3.sett... work here without top.?
		let query = {};
		let failedbefore = false;
		query[method] = parameter;
		$.getJSON(TYPO3.settings.ajaxUrls.dam_request, query)
			.done((data: any): void => {
				switch (method) {
					case 'getContent':
						my.renderBreadcrumb(data);
						my.renderContent(data);
						my.loadThumbs();
						break;
					case 'getThumbnail':
						my.renderThumb(data);
						break;
					default:
						top.TYPO3.Notification.warning('Request failed', 'Unknown method: ' + method);
				}
			})
			.fail((err: any): void => {
				console.log('DigitalAssetManagement request promise fail ' + JSON.stringify(err));
				if (!failedbefore) {
					top.TYPO3.Notification.warning('Request failed', 'Content can not be displayed. ' + err.readyState);
					failedbefore = true;
				}
				my.renderError(err);
			});
	}

	/**
	 *
	 * @param {string} template
	 * @param {object} data
	 * @returns {string}
	 */
	protected static replaceTemplateVars(template: string, data: object): string {
		return template.replace(
				/{([:a-zA-Z_\.-]*)}/g,
				function(m: string, key: string): string {
					// console.log('translate key: '+ key + ', ' + data[key] );
					if (key.indexOf('lll:') === 0) {
						return TYPO3.lang[key.replace(/lll:/, '')] || key;
					} else {
						return data.hasOwnProperty(key) ? data[key] : '###missing prop:' + key + '#';
					}
				}
			);
	}
}

$(DigitalAssetManagementActions.init);

// expose as global object
TYPO3.DigitalAssetManagementActions = DigitalAssetManagementActions;
export = DigitalAssetManagementActions;
