<?php

declare(strict_types=1);

namespace JustCarmen\Webtrees\Module;

use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Tree;
use Fisharebest\Webtrees\View;
use Fisharebest\Webtrees\Media;
use Fisharebest\Webtrees\Registry;
use Illuminate\Support\Collection;
use Fisharebest\Webtrees\Individual;
use Fisharebest\Webtrees\GedcomRecord;
use Fisharebest\Webtrees\FlashMessages;
use Psr\Http\Message\ResponseInterface;
use Fisharebest\Localization\Translation;
use Fisharebest\Webtrees\Http\RequestHandlers\TreePage;
use Illuminate\Database\Query\JoinClause;
use Psr\Http\Message\ServerRequestInterface;
use Fisharebest\Webtrees\Services\TreeService;
use Illuminate\Database\Capsule\Manager as DB;
use Fisharebest\Webtrees\Module\AbstractModule;
use Fisharebest\Webtrees\Module\ModuleConfigTrait;
use Fisharebest\Webtrees\Module\ModuleCustomTrait;
use Fisharebest\Webtrees\Module\ModuleGlobalTrait;
use Fisharebest\Webtrees\Services\MediaFileService;
use Fisharebest\Webtrees\Module\ModuleConfigInterface;
use Fisharebest\Webtrees\Module\ModuleCustomInterface;
use Fisharebest\Webtrees\Module\ModuleGlobalInterface;
use Throwable;

class FancyImagebarModule extends AbstractModule implements ModuleCustomInterface, ModuleConfigInterface, ModuleGlobalInterface
{
    use ModuleCustomTrait;
    use ModuleConfigTrait;
    use ModuleGlobalTrait;

    /** @var MediaFileService */
    private $media_file_service;

    /** @var TreeService */
    private $tree_service;

    /**
     * FancyImagebar constructor.
     *
     * @param MediaFileService  $media_file_service
     * @param TreeService       $tree_service
     */
    public function __construct(MediaFileService $media_file_service, TreeService $tree_service)
    {
        $this->media_file_service = $media_file_service;
        $this->tree_service       = $tree_service;
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
     */
    public function customModuleVersion(): string
    {
        return '2.1.0';
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

         // Add the javascript used by this module in a separate view
         View($this->name() . '::script');
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

        $tree_id = $this->getPreference('last-tree-id');
        if ($tree_id === '') {
            $tree = $this->tree_service->all()->first();
        } else {
            $tree = $this->tree_service->find((int)$tree_id);
        }

        $data_filesystem = Registry::filesystem()->data();

        $media_folders = $this->media_file_service->allMediaFolders($data_filesystem);
        $media_types = $this->media_file_service->mediaTypes();

        $media_folder = $this->getPreference($tree_id . '-media-folder');
        $media_type = $this->getPreference($tree_id . '-media-type');
        $subfolders = $this->getPreference($tree_id . '-subfolders');
        $media_objects = $this->allMedia($tree, str_replace($tree->getPreference('MEDIA_DIRECTORY', 'media/'), "", $media_folder), $subfolders, $media_type, false);

        return $this->viewResponse($this->name() . '::settings', [
            'all_trees'        => $this->tree_service->all(),
            'canvas_height'    => $this->getPreference($tree_id . '-canvas-height', '80'),
            'homepage_only'    => $this->getPreference($tree_id . '-homepage-only'),
            'media_folder'     => $media_folder,
            'media_folders'    => $media_folders,
            'media_list'       => $this->getPreference($tree_id . '-media-list', ''),
            'media_objects'    => $media_objects,
            'media_type'       => $media_type,
            'media_types'      => $media_types,
            'square_thumbs'    => $this->getPreference($tree_id . '-square-thumbs'),
            'subfolders'       => $subfolders,
            'title'            => $this->title(),
            'tree_id'          => $tree_id
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

        // store the preferences in the database when editing the form
        $tree_id = $params['tree-id'];
        $this->setPreference('last-tree-id', $tree_id);

        $this->setPreference($tree_id . '-media-folder', $params['media-folder']);
        $this->setPreference($tree_id . '-subfolders', $params['subfolders'] ?? '0');
        $this->setPreference($tree_id . '-media-type',  $params['media-type']);

        if ($params['save'] === '1') {
            $this->setPreference($tree_id . '-canvas-height',  $params['canvas-height']);
            $this->setPreference($tree_id . '-square-thumbs',  $params['square-thumbs']);
            $this->setPreference($tree_id . '-homepage-only',  $params['homepage-only']);
            $this->setPreference($tree_id . '-media-list', $params['media-list']);

            $message = I18N::translate('The preferences for the module “%s” have been updated.', $this->title());
            FlashMessages::addMessage($message, 'success');
        }

        return redirect($this->getConfigLink());
    }

    /**
     * Raw content, to be added at the end of the <body> element.
     * Typically, this will be <script> elements.
     *
     * Script to put the fancy imagebar in place once it is created
     *
     * @return string
     */
    public function bodyContent(): string
    {
        $body = $this->fancyImagebar();
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
        $request    = app(ServerRequestInterface::class);
        $tree       = $request->getAttribute('tree');

        if ($tree === null) {
            return '';
        }

        $canvas_height = $this->getPreference($tree->id() . '-canvas-height', '80');
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
                    height: ' . $canvas_height_md . 'px;
                }
            }

            @media screen and (max-width: 768px) {
                .jc-fancy-imagebar img {
                    height: ' . $canvas_height_sm . 'px;
                }
            }
            </style>
            <link rel="stylesheet" href="' . e($url) . '">';
    }

    /**
     * Additional/updated translations.
     *
     * @param string $language
     *
     * @return string[]
     */
    public function customTranslations(string $language): array
    {
        $lang_dir   = $this->resourcesFolder() . 'lang/';
        $file       = $lang_dir . $language . '.mo';
        if (file_exists($file)) {
            return (new Translation($file))->asArray();
        } else {
            return [];
        }
    }

    /**
     * Generate the html for the Fancy imagebar
     *
     * @return string
     */
    public function fancyImagebar(): string
    {
        $request = app(ServerRequestInterface::class);

        $tree = $request->getAttribute('tree');
        if ($tree === null) {
            return '';
        }

        $route = $request->getAttribute('route');
        $homepage_only = $this->getPreference($tree->id() . '-homepage-only', '0');
        if ($homepage_only === '1' && $route->name !== TreePage::class) {
           return '';
        }

        $data_filesystem = Registry::filesystem()->data();
        $data_folder = Registry::filesystem()->dataName();

        $wt_media_folder = $tree->getPreference('MEDIA_DIRECTORY', 'media/');

        // Set default values in case the settings are not stored in the database yet
        $canvas_height   = $this->getPreference($tree->id() . '-canvas-height', '80');
        $subfolders      = $this->getPreference($tree->id() . '-subfolders', '1');
        $media_type      = $this->getPreference($tree->id() . '-media-type', '');
        $square_thumbs   = $this->getPreference($tree->id() . '-square-thumbs', '0');
        $media_list      = $this->getPreference($tree->id() . '-media-list', '');

        // how much thumbnails do we need at most to fill up the canvas.
        // If square is chosen as an option then we don't know the width of the thumbnails.
        // Play safe and use 0.5 * thumb height as thumb width. This means we assume an thumbnail with a height of 80px is 40px width.
        // Most thumbnails will be larger than that. 3840 is the maximum screensize we will take into account.
        $canvas_width  = 3840; // Add support for 4K displays
        $canvas_height = $this->getPreference($tree->id() . '-canvas-height', '80');
        $num_thumbs    = (int)ceil($canvas_width / ($canvas_height * 0.5));

        // strip out the default media directory from the folder path. It is not stored in the database
        $folder = str_replace($wt_media_folder, "", $this->getPreference($tree->id() . '-media-folder'));

        // include or exclude subfolders
        $subfolders = $subfolders === '1' ? 'include' : 'exclude';

        // pull the records from the database
        $records = $this->allMedia($tree, $folder, $subfolders, $media_type, true);

        if (count($records) === 0) {
            return '';
        }

        // Get the thumbnail resources
        $resources = array();
        $arr_media_list = explode(',', $media_list);
        foreach ($records as $record) {
            if (count($resources) < $num_thumbs) {
                $i = 0; // counter for multiple media files in a media object
                foreach ($record->mediaFiles() as $media_file) {
                    $i++;
                    if (count($arr_media_list) > 0) {
                        $process_image = in_array($record->xref() . '[' . $i . ']', $arr_media_list) ? true : false;
                    } else {
                        $process_image = in_array($media_file->mimeType(), ['image/jpeg', 'image/png'], true) ? true : false;
                    }

                    if ($process_image === true && $media_file->fileExists($data_filesystem)) {
                        $file        = $data_folder . $wt_media_folder . $media_file->filename();
                        $fancy_thumb = $this->fancyThumb($file, $canvas_height, $square_thumbs);

                        if (!is_null($fancy_thumb)) {
                            $resources[] = [
                                'image'     => $fancy_thumb,
                                'linked'    => $this->getLinkedObject($record)
                            ];
                        }
                    }
                }
            }
        }

        // Repeat items if neccessary to fill up the Fancy Imagebar
        if (count($resources) < $num_thumbs) {
            // see: https://stackoverflow.com/questions/2963777/how-to-repeat-an-array-in-php
            // works in php 5.6+
            shuffle($resources); // randomize the order of images in the Fancy imagebar before filling up
            $resources = array_merge(...array_fill(0, $num_thumbs - count($resources), $resources));
        }

        return $this->createFancyImagebar($resources, $canvas_width, $canvas_height);
    }

    /**
     * Generate a list of all the media objects matching the criteria in a current tree.
     * Source: app\Module\MediaListModule.php
     *
     * @param Tree   $tree
     * @param string $folder
     * @param string $subfolders
     * @param string $type
     * @param bool $random
     *
     * @return Collection<Media>
     */
    private function allMedia(Tree $tree, string $folder, string $subfolders, string $type, bool $random): Collection
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

        if ($random) {
            $query->inRandomOrder();
        }

        return $query
            ->get()
            ->map(Registry::mediaFactory()->mapper($tree))
            ->uniqueStrict()
            ->filter(GedcomRecord::accessFilter());
    }

    /**
     * Create the Fancy Imagebar + html output
     *
     * @param array $source_images
     * @param string $canvas_width
     * @param string $canvas_height
     *
     * @return string
     */
    private function createFancyImagebar($source_images, $canvas_width, $canvas_height): string
    {
        // create the FancyImagebar canvas to put the thumbs on
        $fancy_imagebar_canvas = imagecreatetruecolor((int) $canvas_width, (int) $canvas_height);

        $fancy_map = [];
        $pos = 0;
        foreach ($source_images as $source) {

            $image  = $source['image'];
            $linked = $source['linked'];

            $x1  = $pos;
            $x2  = $x1 + imagesx($image);
            $pos = $pos + imagesx($image);

            // copy the images (thumbnails) to the canvas
            // imagecopy (resource $dst_im , resource $src_im , int $dst_x , int $dst_y , int $src_x , int $src_y , int $src_w , int $src_h)
            imagecopy($fancy_imagebar_canvas, $image, $x1, 0, 0, 0, imagesx($image), (int) $canvas_height);

            // prepare the map
            if ($linked !== null) {
                $lifespan = $linked instanceof Individual ? ' (' . $linked->lifespan() . ')' : '';
                $fancy_map[] = [
                    'coords' => [
                        'x1' => $x1,
                        'y1' => '0',
                        'x2' => $x2,
                        'y2' => $canvas_height
                    ],
                    'title' => strip_tags($linked->fullName() . $lifespan),
                    'url'   => e($linked->url())
                ];
            }
        }

        // Output
        ob_start();
        imagejpeg($fancy_imagebar_canvas);
        $fancy_imagebar = ob_get_clean();

        return view($this->name() . '::fancy-imagebar', [
            'fancy_imagebar' => $fancy_imagebar,
            'fancy_map'      => $fancy_map,
            'canvas_height'  => $canvas_height
        ]);
    }

    /**
     * load image from file
     * return false if image could not be loaded
     *
     * @param string $file
     */
    private function loadImage($file)
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
     * @param string $file
     * @param string $canvas_height
     * @param string $canvas_width
     *
     * https://www.jveweb.net/en/archives/2010/09/how-to-create-cropped-and-scaled-thumbnails-in-php.html
     */
    private function fancyThumb($file, $canvas_height, $square_thumbs)
    {
        // error handling to prevent bad images from loading
        try {

            $source_image = $this->loadImage($file);
            list($source_width, $source_height) = getimagesize($file);
            $source_ratio = $source_width / $source_height;

        } catch (Throwable $exception) {

            return null;

        }

        // Cases have been reported where a false source image produces a fatal error at
        // the imagecopyresampled function: imagecopyresampled() expects parameter 2 to be resource, bool given
        // This source image got through the try/catch section
        if ($source_image === false) {
            return null;
        }

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
            $thumb_width  = $canvas_height * $source_ratio;
            $thumb_height = $canvas_height;
        }

        $thumb = ImageCreateTrueColor((int)$thumb_width, (int)$thumb_height);
        imagecopyresampled($thumb, $source_image, 0, 0, (int)$source_x, (int)$source_y, (int)$thumb_width, (int)$thumb_height, (int)$source_width, (int)$source_height);

        imagedestroy($source_image);

        return $thumb; // resource
    }

    /**
     * Which objects are linked to this file?
     * return the first linked object (indi, fam or source)
     *
     * @param Media $media
     * @return GedcomRecord|null
     */
    private function getLinkedObject(Media $media): ?GedcomRecord
    {
        // return the first link found
        return
            $media->linkedIndividuals('OBJE')->first() ??
            $media->linkedFamilies('OBJE')->first() ??
            $media->linkedsources('OBJE')->first();
    }
};
