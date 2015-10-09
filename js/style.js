/*
 * webtrees: online genealogy
 * Copyright (C) 2015 webtrees development team
 * Copyright (C) 2015 JustCarmen
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

/* global $theme, $childtheme
 * 
 * $theme is the parent theme (in case of a custom child theme)
 * to modify the theme for a childtheme, modify it's parent unless the childtheme needs a different css
 * In that case, copy the parent code/css to make a new child code/css.
 * 
 */

jQuery.fn.fibStyle = function() {
	
	this.each(function() {
		jQuery(this).css({
			"clear": "both",
			"overflow": "hidden"
		});

		// first check childtheme style. If not found use the style of the parent.
		switch ($childtheme) {
			// colors theme is a childtheme of clouds theme.
			case 'colors':
				jQuery(this).append('<div class="divider" style="background-color:#999;height:1px;margin-top:1px">');
				break;
			default:
				switch ($theme) {
					case 'clouds':
						jQuery(this).css({
							"margin": "10px 10px 0 10px",
							"border": "1px solid #003399"
						});
						jQuery(this).find('img').css({
							"margin-bottom": "-2px"
						});
						break;
					case 'fab':
						jQuery(this).css({
							"border": "#A9A9A9 1px solid",
							"border-radius": "3px",
							"margin": "0 3px"
						});
						jQuery(this).find('img').css({
							"margin-bottom": "-3px"
						});
						break;
					case 'justblack':
						jQuery(this).css({
							"margin-top": "-1px"
						}).append('<div class="divider" style="margin-top:3px">');
						break;
					case 'justlight':
						jQuery(this).find('img').css({
							"border-top": "5px solid #428bca",
							"border-bottom": "5px solid #428bca"
						});
						break;
					case 'minimal':
						jQuery(this).css({
							"padding-top": "2px"
						}).append('<div class="divider" style="background-color:#555555;height:1px">');
						break;
					case 'webtrees':
						jQuery(this).append('<div class="divider" style="background-color:#81A9CB;height:2px;margin-top:3px">');
						break;
					case 'xenea':
						jQuery(this).append('<div class="divider" style="background-color:#0073CF;height:2px;margin:7px 0 15px">');
						break;
					default:
						// this is a custom theme directly derived from AbstractTheme. Add the code for this theme in the $theme switch.
				}
		}
	});
}