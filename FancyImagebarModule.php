<?php

declare(strict_types=1);

namespace JustCarmen\Webtrees\Module;

use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Tree;
use Fisharebest\Webtrees\View;
use Fisharebest\Webtrees\Factory;
use Fisharebest\Webtrees\Webtrees;
use Illuminate\Support\Collection;
use Fisharebest\Webtrees\GedcomRecord;
use Fisharebest\Webtrees\FlashMessages;
use Psr\Http\Message\ResponseInterface;
use Illuminate\Database\Query\JoinClause;
use League\Flysystem\FilesystemInterface;
use Psr\Http\Message\ServerRequestInterface;
use Illuminate\Database\Capsule\Manager as DB;
use Fisharebest\Webtrees\Module\AbstractModule;
use Fisharebest\Webtrees\Module\ModuleConfigTrait;
use Fisharebest\Webtrees\Module\ModuleCustomTrait;
use Fisharebest\Webtrees\Module\ModuleGlobalTrait;
use Fisharebest\Webtrees\Services\MediaFileService;
use Fisharebest\Webtrees\Module\ModuleConfigInterface;
use Fisharebest\Webtrees\Module\ModuleCustomInterface;
use Fisharebest\Webtrees\Module\ModuleGlobalInterface;

class FancyImagebarModule extends AbstractModule implements ModuleCustomInterface, ModuleConfigInterface, ModuleGlobalInterface
{
    use ModuleCustomTrait;
    use ModuleConfigTrait;
    use ModuleGlobalTrait;

    /** @var MediaFileService */
    private $media_file_service;

    /** @var Webtrees::VERSION */
    private $wt_version;

    /**
     * FancyImagebar constructor.
     *
     * @param MediaFileService  $media_file_service
     */
    public function __construct(MediaFileService $media_file_service)
    {
        $this->media_file_service = $media_file_service;
    }

    /**
     * How should this module be identified in the control panel, etc.?
     *
     * @return string
     */
    public function title(): string
    {
        return I18N::translate('Fancy Imagebar');
    }

    /**
     * A sentence describing what this module does.
     *
     * @return string
     */
    public function description(): string
    {
        return I18N::translate('An imagebar with small images between header and content.');
    }

    /**
     * {@inheritDoc}
     * @see \Fisharebest\Webtrees\Module\ModuleCustomInterface::customModuleAuthorName()
     */
    public function customModuleAuthorName(): string
    {
        return 'JustCarmen';
    }

    /**
     * {@inheritDoc}
     * @see \Fisharebest\Webtrees\Module\ModuleCustomInterface::customModuleVersion()
     *
     * We use a system where the version number is equal to the latest version of webtrees
     * Interim versions get an extra sub number
     *
     * The dev version is always one step above the latest stable version of this module
     * The subsequent stable version depends on the version number of the latest stable version of webtrees
     *
     */
    public function customModuleVersion(): string
    {
        return '2.0.8-dev';
    }

    /**
     * A URL that will provide the latest stable version of this module.
     *
     * @return string
     */
    public function customModuleLatestVersionUrl(): string
    {
        return 'https://raw.githubusercontent.com/JustCarmen/webtrees-fancy-imagebar/master/latest-version.txt';
    }

    /**
     * {@inheritDoc}
     * @see \Fisharebest\Webtrees\Module\ModuleCustomInterface::customModuleSupportUrl()
     */
    public function customModuleSupportUrl(): string
    {
        return 'https://github.com/justcarmen/webtrees-fancy-imagebar/issues';
    }

    /**
     * Bootstrap.  This function is called on *enabled* modules.
     * It is a good place to register routes and views.
     *
     * @return void
     */
    public function boot(): void
    {
        // Register a namespace for our views.
        View::registerNamespace($this->name(), $this->resourcesFolder() . 'views/');

        // What webtrees version are we on?
        $this->wt_version = (int)str_replace(['_dev', '.'], '', Webtrees::VERSION);
    }

    /**
     * Where does this module store its resources
     *
     * @return string
     */
    public function resourcesFolder(): string
    {
        return __DIR__ . '/resources/';
    }

    /**
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function getAdminAction(ServerRequestInterface $request): ResponseInterface
    {
        $this->layout = 'layouts/administration';

        // changed as of wt-2.0.8-dev.
        if ($this->wt_version > 207) {
            $data_filesystem = Factory::filesystem()->data();
        } else {
            $data_filesystem = $request->getAttribute('filesystem.data');
            assert($data_filesystem instanceof FilesystemInterface);
        }

        $media_folders = $this->media_file_service->allMediaFolders($data_filesystem);
        $media_types = $this->media_file_service->mediaTypes();

        return $this->viewResponse($this->name() . '::settings', [
            'title'            => $this->title(),
            'media_folders'    => $media_folders,
            'media_types'      => $media_types,
            'media_folder'     => $this->getPreference('media-folder'),
            'subfolders'       => $this->getPreference('subfolders'),
            'media_type'       => $this->getPreference('media-type'),
            'canvas_height'    => $this->getPreference('canvas-height', '80'),
            'square_thumbs'    => $this->getPreference('square-thumbs')
        ]);
    }

    /**
     * Save the user preference.
     *
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function postAdminAction(ServerRequestInterface $request): ResponseInterface
    {
        $params = (array) $request->getParsedBody();

        // store the preferences in the database
        $this->setPreference('media-folder', $params['media-folder']);
        $this->setPreference('subfolders', $params['subfolders'] ?? '0');
        $this->setPreference('media-type',  $params['media-type']);
        $this->setPreference('canvas-height',  $params['canvas-height']);
        $this->setPreference('square-thumbs',  $params['square-thumbs']);

        $message = I18N::translate('The preferences for the module “%s” have been updated.', $this->title());
        FlashMessages::addMessage($message, 'success');

        return redirect($this->getConfigLink());
    }

    /**
     * Raw content, to be added at the end of the <body> element.
     * Typically, this will be <script> elements.
     *
     * @return string
     */
    public function bodyContent(): string
    {
        $body = $this->fancyImagebarHtml();
        $body .= '<script>';
        $body .= '$(".wt-main-wrapper").prepend($(".jc-fancy-imagebar"))';
        $body .= '</script>';

        return $body;
    }

    /**
     * Raw content, to be added at the end of the <head> element.
     * Typically, this will be <link> and <meta> elements.
     *
     * @return string
     */
    public function headContent(): string
    {
        $canvas_height = $this->getPreference('canvas-height', '80');
        $canvas_height_md = 0.85 * $canvas_height;
        $canvas_height_sm = 0.75 * $canvas_height;

        $url = $this->assetUrl('css/style.css');

        return '
            <style>
            .jc-fancy-imagebar img {
                height: ' . $canvas_height . 'px;
            }

            @media screen and (max-width: 992px) {
                .jc-fancy-imagebar img {
                    height: ' . $canvas_height_md. 'px;
                }
            }

            @media screen and (max-width: 768px) {
                .jc-fancy-imagebar img {
                    height: '. $canvas_height_sm . 'px;
                }
            }
            </style>
            <link rel="stylesheet" href="' . e($url) . '">';
    }

    /**
     * Generate the html for the Fancy imagebar
     */
    public function fancyImagebarHtml(): string
    {
        $request = app(ServerRequestInterface::class);
        $tree = $request->getAttribute('tree');
        assert($tree instanceof Tree);

        // changed in wt-2.0.8-dev.
        if ($this->wt_version > 207) {
            $data_filesystem = Factory::filesystem()->data();
            $data_folder = Factory::filesystem()->dataName();
        } else {
            $data_filesystem = $request->getAttribute('filesystem.data');
            assert($data_filesystem instanceof FilesystemInterface);

            $data_folder = $request->getAttribute('filesystem.data.name');
            assert(is_string($data_folder));
        }

        $wt_media_folder = $tree->getPreference('MEDIA_DIRECTORY', 'media/');

        // Set default values in case the settings are not stored in the database yet
        $canvas_height   = $this->getPreference('canvas-height', '80');
        $subfolders      = $this->getPreference('subfolders', '1');
        $media_type      = $this->getPreference('media-type', '');
        $square_thumbs   = $this->getPreference('square-thumbs', '0');

        // how much images do we need at most to fill up the canvas. If square is unwanted then we don't know the width of the images.
        // Play safe and use 0.75 thumb height as thumb width
        // 2400 is the maximum screensize we will take into account.
        $canvas_width  = 2400;
        $canvas_height = $this->getPreference('canvas-height', '80');
        $num_thumbs    = (int)ceil($canvas_width / ($canvas_height * 0.75));

        // strip out the default media directory from the folder path. It is not stored in the database
        $folder = str_replace($wt_media_folder, "", $this->getPreference('media-folder'));

        // include or exclude subfolders
        $subfolders = $subfolders === '1' ? 'include' : 'exclude';

        // pull the records from the database
        $records = $this->allMedia($tree, $folder, $subfolders, $media_type, $num_thumbs);

        // Get the thumbnail resources
        $resources = array();
        foreach ($records as $record) {
            foreach ($record->mediaFiles() as $media_file) {

                if ($media_file->isImage() && $media_file->fileExists($data_filesystem)) {
                    $file        = $data_folder . $wt_media_folder . $media_file->filename();
                    $resources[] = $this->fancyThumb($file, $canvas_height, $square_thumbs);
                    //die(var_dump($record->xref()));
                }
            }
        }

        // Repeat items if neccessary to fill up the Fancy Imagebar
        if (count($resources) < $num_thumbs) {
            // see: https://stackoverflow.com/questions/2963777/how-to-repeat-an-array-in-php
            // works in php 5.6+
            $resources = array_merge(...array_fill(0, $num_thumbs - count($resources), $resources));
            shuffle($resources);
        }

        // Generate the response.
        $fancy_imagebar = $this->createFancyImagebar($resources, $canvas_width, $canvas_height);

        return view($this->name() . '::fancy-imagebar', [
            'fancy_imagebar' => $fancy_imagebar
        ]);
    }

    /**
     * Generate a list of all the media objects matching the criteria in a current tree.
     * Source: app\Module\MediaListModule.php
     *
     * SELECT * FROM `wt_media_file` WHERE `m_file`=8 AND `multimedia_format`='jpg' and `source_media_type`='photo'
     *
     * @param Tree   $tree       find media in this tree
     * @param string $format     'jpg'
     * @param string $type       source media type = 'photo'
     *
     * @return Collection<Media>
     */
    private function allMedia(Tree $tree, string $folder, string $subfolders, string $type, int $num_thumbs): Collection
    {
        $query = DB::table('media')
            ->join('media_file', static function (JoinClause $join): void {
                $join
                    ->on('media_file.m_file', '=', 'media.m_file')
                    ->on('media_file.m_id', '=', 'media.m_id');
            })
            ->where('media.m_file', '=', $tree->id())
            ->where('multimedia_file_refn', 'LIKE', $folder . '%')
            ->whereIn('multimedia_format', ['jpg', 'jpeg', 'png']);

        if ($subfolders === 'exclude') {
            $query->where('multimedia_file_refn', 'NOT LIKE', $folder . '%/%');
        }

        if ($type) {
            $query->where('source_media_type', '=', $type);
        }

        return $query
            ->inRandomOrder()->limit($num_thumbs)->get()
            ->map(Factory::media()->mapper($tree))
            ->uniqueStrict()
            ->filter(GedcomRecord::accessFilter());
    }

    /**
     * Create the Fancy Imagebar
     *
     * @param type $source_images
     * @param type $canvas_width
     * @param type $canvas_height
     *
     * return image resource
     */
    private function createFancyImagebar($source_images, $canvas_width, $canvas_height)
    {
        // create the FancyImagebar canvas to put the thumbs on
        $fancy_imagebar_canvas = imagecreatetruecolor((int) $canvas_width, (int) $canvas_height);

        $pos = 0;
        foreach ($source_images as $image) {
            $x = $pos;
            $pos = $pos + imagesx($image);

            // copy the images (thumbnails) to the canvas
            imagecopy($fancy_imagebar_canvas, $image, $x, 0, 0, 0, imagesx($image), (int) $canvas_height);
        }

        ob_start();
        imagejpeg($fancy_imagebar_canvas);
        $fancy_imagebar = ob_get_clean();

        return $fancy_imagebar;
    }

    /**
     * load image from file
     * return false if image could not be loaded
     *
     * @param type $file
     * @return boolean
     */
    public function loadImage($file)
    {
        $size = getimagesize($file);
        switch ($size["mime"]) {
            case "image/jpeg":
                $image = imagecreatefromjpeg($file);
                break;
            case "image/png":
                $image = imagecreatefrompng($file);
                break;
            default:
                $image = false;
                break;
        }
        return $image;
    }

    /**
     * Create a thumbnail
     *
     * @param type $mediaobject
     * @return thumbnail
     *
     * https://www.jveweb.net/en/archives/2010/09/how-to-create-cropped-and-scaled-thumbnails-in-php.html
     */
    private function fancyThumb($file, $canvas_height, $square_thumbs)
    {
        $source_image = $this->loadImage($file);
        list($source_width, $source_height) = getimagesize($file);

        $source_ratio = $source_width / $source_height;

        $source_x = 0;
        $source_y = 0;

        // if square thumbnails are wanted then resize and crop the original image
        if ($square_thumbs) {
            $thumb_width = $thumb_height = $canvas_height;

            if ($source_ratio < 1) {
                $source_y = ceil(($source_height - $source_width) / 2);
                $source_height = $source_width;
            }

            if ($source_ratio > 1) {
                $source_x = ceil(($source_width - $source_height) / 2);
                $source_width = $source_height;
            }
        } else {
            if ($source_ratio < 1) {
                $thumb_width  = $canvas_height;
                $thumb_height = $canvas_height / $source_ratio;
            } else {
                $thumb_width  = $canvas_height * $source_ratio;
                $thumb_height = $canvas_height;
            }
        }

        $thumb = ImageCreateTrueColor((int)$thumb_width, (int)$thumb_height);
        imagecopyresampled($thumb, $source_image, 0, 0, (int)$source_x, (int)$source_y, (int)$thumb_width, (int)$thumb_height, (int)$source_width, (int)$source_height);

        imagedestroy($source_image);

        return $thumb; // resource
    }
};
