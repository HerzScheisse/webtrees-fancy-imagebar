<?php
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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */
namespace JustCarmen\WebtreesAddOns\FancyImagebar;

use Fisharebest\Webtrees\Database;
use Fisharebest\Webtrees\File;
use Fisharebest\Webtrees\Filter;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Media;
use Fisharebest\Webtrees\Tree;
use Rhumsaa\Uuid\Uuid;

/**
 * Class Fancy Imagebar
 */
class FancyImagebarClass extends FancyImagebarModule {
	
	/**
	 * Set the default module options
	 * 
	 * @param type $key
	 * @return string
	 */
	private function setDefault($key) {
		$FIB_DEFAULT = array(
			'IMAGES'	 => '1', // All images
			'HOMEPAGE'	 => '1',
			'MYPAGE'	 => '1',
			'ALLPAGES'	 => '0',
			'RANDOM'	 => '1',
			'TONE'		 => '0',
			'SEPIA'		 => '30',
			'SIZE'		 => '60'
		);
		return $FIB_DEFAULT[$key];
	}
	
	/**
	 * Get module options
	 * 
	 * @param type $k
	 * @return type
	 */
	protected function options($k) {
		$FIB_OPTIONS = unserialize($this->getSetting('FIB_OPTIONS'));
		$key = strtoupper($k);

		if (empty($FIB_OPTIONS[$this->getTreeId()]) || (is_array($FIB_OPTIONS[$this->getTreeId()]) && !array_key_exists($key, $FIB_OPTIONS[$this->getTreeId()]))) {
			return $this->setDefault($key);
		} else {
			return($FIB_OPTIONS[$this->getTreeId()][$key]);
		}
	}
	
	/**
	 * Get the current tree id
	 * 
	 * @global type $WT_TREE
	 * @return type
	 */
	protected function getTreeId() {
		global $WT_TREE;

		if ($WT_TREE) {
			$tree = $WT_TREE->findByName(Filter::get('ged'));
			if ($tree) {
				return $tree->getTreeId();
			} else {
				return $WT_TREE->getTreeId();
			}
		}
	}
	
	/**
	 * Get a list of all the media xrefs
	 * 
	 * @return list
	 */
	protected function getXrefs() {
		$sql = "SELECT m_id AS xref, m_file AS tree_id FROM `##media` WHERE m_file = :tree_id AND m_type = 'photo'";
		$args = array(
			'tree_id' => $this->getTreeId()
		);

		$rows = Database::prepare($sql)->execute($args)->fetchAll();
		$list = array();
		foreach ($rows as $row) {
			$list[] = $row->xref;
		}
		return $list;
	}
	
	/**
	 * Use Json to load images in control panel (datatable)* 
	 * 
	 */
	protected function loadJson() {
		$start = Filter::getInteger('start');
		$length = Filter::getInteger('length');

		if ($length > 0) {
			$LIMIT = " LIMIT " . $start . ',' . $length;
		} else {
			$LIMIT = "";
		}

		$sql = "SELECT SQL_CACHE SQL_CALC_FOUND_ROWS m_id AS xref, m_file AS tree_id FROM `##media` WHERE m_file = :tree_id AND m_type = 'photo'" . $LIMIT;
		$args = array(
			'tree_id' => $this->getTreeId()
		);

		$rows = Database::prepare($sql)->execute($args)->fetchAll();

		// Total filtered/unfiltered rows
		$recordsTotal = $recordsFiltered = Database::prepare("SELECT FOUND_ROWS()")->fetchOne();

		$data = array();
		foreach ($rows as $row) {
			$tree = Tree::findById($row->tree_id);
			$media = Media::getInstance($row->xref, $tree);
			$data[] = array(
				$this->displayImage($media)
			);
		}
		header('Content-type: application/json');
		echo json_encode(array(// See http://www.datatables.net/usage/server-side
			'draw'				 => Filter::getInteger('draw'), // String, but always an integer
			'recordsTotal'		 => $recordsTotal,
			'recordsFiltered'	 => $recordsFiltered,
			'data'				 => $data
		));
		exit;
	}
	
	/**
	 * Display images in control panel
	 * 
	 * @param type $media
	 * @return type
	 */
	private function displayImage($media) {
		if (file_exists($media->getServerFilename()) && getimagesize($media->getServerFilename()) && ($media->mimeType() == 'image/jpeg' || $media->mimeType() == 'image/png')) {
			$image = $this->fancyThumb($media, 60, 60);
			if ($this->options('images') == 1) {
				$img_checked = ' checked="checked"';
			} elseif (is_array($this->options('images')) && in_array($media->getXref(), $this->options('images'))) {
				$img_checked = ' checked="checked"';
			} else {
				$img_checked = "";
			}

			// ouput all thumbs as jpg thumbs (transparent png files are not possible in the Fancy Imagebar, so there is no need to keep the mimeType png).
			ob_start(); imagejpeg($image, null, 100); $newImage = ob_get_clean();
			return '<img src="data:image/jpeg;base64,' . base64_encode($newImage) . '" alt="' . $media->getXref() . '" title="' . strip_tags($media->getFullName()) . '"/>
					<label class="checkbox"><input type="checkbox" value="' . $media->getXref() . '"' . $img_checked . '></label>';
		} else {
			// this image doesn't exist on the server or is not a valid image
			$mime_type = str_replace('/', '-', $media->mimeType());
			return '<div class="no-image"><i class="icon-mime-' . $mime_type . '" title="' . I18N::translate('The image “%s” doesn’t exist or is not a valid image', strip_tags($media->getFullName()) . ' (' . $media->getXref() . ')') . '"></i></div>
				<label class="checkbox"><input type="checkbox" value="" disabled="disabled"></label>';
		}
	}
	
	/**
	 * Get the Fancy Imagebar with chosen images and options
	 * 
	 * @return html
	 */
	protected function getFancyImagebar() {
		$width = $height = $this->options('size');

		$thumbnails = $this->getThumbnails();

		if (count($thumbnails) === 0) {
			return false;
		}

		// be sure the imagebar will be big enough for wider screens
		$newArray = array();

		// determine how many thumbs we need (based on a users screen of 2400px);
		$fib_length = ceil(2400 / $this->options('size'));
		while (count($newArray) <= $fib_length) {
			$newArray = array_merge($newArray, $thumbnails);
		}
		// reduce the new array to the desired length (as there might be too many elements in the new array
		$images = array_slice($newArray, 0, $fib_length);

		$fancyImagebar = $this->createFancyImagebar($images, $width, $height, $fib_length);
		if ($this->options('tone') == 0) {
			$fancyImagebar = $this->fancyImageBarSepia($fancyImagebar, $this->options('sepia'));
		}
		if ($this->options('tone') == 1) {
			$fancyImagebar = $this->fancyImageBarSepia($fancyImagebar, 0);
		}
		ob_start(); imagejpeg($fancyImagebar, null, 100); $NewFancyImageBar = ob_get_clean();
		$html = '<div id="fancy_imagebar" style="display:none">
					<img alt="fancy_imagebar" src="data:image/jpeg;base64,' . base64_encode($NewFancyImageBar) . '">
				</div>';

		// output
		return $html;
	}
	
	/**
	 * Get the medialist from the database
	 * 
	 * @return list
	 */
	private function fancyImagebarMedia() {
		$images = $this->options('images');

		$sql = "SELECT SQL_CACHE m_id AS xref, m_file AS tree_id FROM `##media` WHERE m_file='" . $this->getTreeId() . "'";
		if ($images === '1') {
			$sql .= " AND m_type='photo'";
		} else {
			// single quotes needed around id's for sql statement.
			foreach ($images as $image) {
				$images_sql[] = '\'' . $image . '\'';
			}
			$sql .= " AND m_id IN (" . implode(',', $images_sql) . ")";
		}
		$sql .= $this->options('random') == 1 ? " ORDER BY RAND()" : " ORDER BY m_id DESC";

		$rows = Database::prepare($sql)->execute()->fetchAll();
		$list = array();
		foreach ($rows as $row) {
			$tree = Tree::findById($row->tree_id);
			$media = Media::getInstance($row->xref, $tree);
			if ($media->canShow() && ($media->mimeType() == 'image/jpeg' || $media->mimeType() == 'image/png')) {
				$list[] = $media;
			}
		}
		return $list;
	}
	
	/**
	 * Get the fib-cache directory
	 * 
	 * @return directory name
	 */
	protected function getCacheDir() {
		return WT_DATA_DIR . 'fib_cache' . DIRECTORY_SEPARATOR . $this->getTreeId() . DIRECTORY_SEPARATOR;
	}
	
	/**
	 * Check if thumbnails from cache should be recreated	 * 
	 */
	private function useImagesFromCache() {
		$cache_dir = $this->getCacheDir();
		if (!file_exists($cache_dir) || count(glob($cache_dir . '*')) === 0) {
			$this->createThumbnails();
		} else {
			foreach (glob($cache_dir . '*') as $cache_filename) {
				if (is_file($cache_filename)) {
					$filemtime = filemtime($cache_filename);
				} else {
					$filemtime = 0;
				}

				if (time() > $filemtime + 86400) { // 86400 = 1 day (24 * 60 * 60)
					$this->createThumbnails();
					break;
				}
			}
		}
	}
	
	/**
	 * (Re)create thumbnails to cache
	 */
	protected function createThumbnails() {
		$cache_dir = $this->getCacheDir();
		if (file_exists($cache_dir)) {
			// remove all old cached files from the server
			foreach (glob($cache_dir . '*') as $file) {
				if (is_file($file)) {
					unlink($file);
				}
			}
		} else {
			File::mkdir($cache_dir);
		}

		$mediaobjects = $this->fancyImagebarMedia();
		foreach ($mediaobjects as $mediaobject) {
			if (file_exists($mediaobject->getServerFilename())) {
				$thumbnail = $this->fancyThumb($mediaobject);
				$cache_filename = $cache_dir . Uuid::uuid4() . '.jpg';
				if ($thumbnail) {
					imagejpeg($thumbnail, $cache_filename);
				}
			}
		}
	}
	
	/**
	 * Get the thumbnails from cache
	 * 
	 * @return array with thumbnails
	 */
	private function getThumbnails() {
		$this->useImagesFromCache();
		foreach (glob($this->getCacheDir() . '*') as $cache_filename) {
			$thumbnails[] = imagecreatefromjpeg($cache_filename);
		}
		if ($this->options('random') === '1') {
			shuffle($thumbnails);
		}
		return $thumbnails;
	}
	
	/**
	 * Create a thumbnail
	 * 
	 * @param type $mediaobject
	 * @return thumbnail
	 */

	private function fancyThumb($mediaobject) {
		$filename = $mediaobject->getServerFilename();
		$type = $mediaobject->mimeType();

		//getting the image dimensions
		list($imagewidth, $imageheight) = getimagesize($filename);
		switch ($type) {
			case 'image/jpeg':
				$image = imagecreatefromjpeg($filename);
				break;
			case 'image/png':
				$image = imagecreatefrompng($filename);
				break;
		}

		$ratio = $imagewidth / $imageheight;
		$thumbwidth = $thumbheight = $this->options('size');

		if ($thumbwidth / $thumbheight > $ratio) {
			$new_height = $thumbwidth / $ratio;
			$new_width = $thumbwidth;
		} else {
			$new_width = $thumbheight * $ratio;
			$new_height = $thumbheight;
		}

		// transparent png files are not possible in the Fancy Imagebar, so no extra code needed.
		$new_image = imagecreatetruecolor(round($new_width), round($new_height));
		imagecopyresampled($new_image, $image, 0, 0, 0, 0, $new_width, $new_height, $imagewidth, $imageheight);

		$thumb = imagecreatetruecolor($thumbwidth, $thumbheight);
		imagecopyresampled($thumb, $new_image, 0, 0, 0, 0, $thumbwidth, $thumbheight, $thumbwidth, $thumbheight);

		imagedestroy($new_image);
		imagedestroy($image);
		return $thumb;
	}
	
	/**
	 * Create the Fancy Imagebar
	 * 
	 * @param type $srcImages
	 * @param type $thumbWidth
	 * @param type $thumbHeight
	 * @param type $numberOfThumbs
	 * @return Fancy Imagebar
	 */
	private function createFancyImagebar($srcImages, $thumbWidth, $thumbHeight, $numberOfThumbs) {
		// defaults
		$pxBetweenThumbs = 0;
		$leftOffSet = $topOffSet = 0;
		$canvasWidth = ($thumbWidth + $pxBetweenThumbs) * $numberOfThumbs;
		$canvasHeight = $thumbHeight;

		// create the FancyImagebar canvas to put the thumbs on
		$fancyImagebar = imagecreatetruecolor($canvasWidth, $canvasHeight);

		foreach ($srcImages as $index => $thumb) {
			$x = ($index % $numberOfThumbs) * ($thumbWidth + $pxBetweenThumbs) + $leftOffSet;
			$y = floor($index / $numberOfThumbs) * ($thumbWidth + $pxBetweenThumbs) + $topOffSet;

			imagecopy($fancyImagebar, $thumb, $x, $y, 0, 0, $thumbWidth, $thumbHeight);
		}
		return $fancyImagebar;
	}
	
	/**
	 * Use sepia (optional)
	 * 
	 * @param type $fancyImagebar
	 * @param type $depth
	 * @return Fancy Imagebar in Sepia
	 */
	private function fancyImagebarSepia($fancyImagebar, $depth) {
		imagetruecolortopalette($fancyImagebar, 1, 256);

		for ($c = 0; $c < 256; $c++) {
			$col = imagecolorsforindex($fancyImagebar, $c);
			$new_col = floor($col['red'] * 0.2125 + $col['green'] * 0.7154 + $col['blue'] * 0.0721);
			if ($depth > 0) {
				$r = $new_col + $depth;
				$g = floor($new_col + $depth / 1.86);
				$b = floor($new_col + $depth / -3.48);
			} else {
				$r = $new_col;
				$g = $new_col;
				$b = $new_col;
			}
			imagecolorset($fancyImagebar, $c, max(0, min(255, $r)), max(0, min(255, $g)), max(0, min(255, $b)));
		}
		return $fancyImagebar;
	}

}
