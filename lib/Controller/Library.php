<?php
/**
 * Copyright (C) 2020 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - http://www.xibo.org.uk
 *
 * This file is part of Xibo.
 *
 * Xibo is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version.
 *
 * Xibo is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Xibo.  If not, see <http://www.gnu.org/licenses/>.
 */
namespace Xibo\Controller;

use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Stream;
use Mimey\MimeTypes;
use Respect\Validation\Validator as v;
use Slim\Http\Response as Response;
use Slim\Http\ServerRequest as Request;
use Slim\Routing\RouteContext;
use Slim\Views\Twig;
use Stash\Interfaces\PoolInterface;
use Stash\Invalidation;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Xibo\Entity\Media;
use Xibo\Entity\Widget;
use Xibo\Factory\DataSetFactory;
use Xibo\Factory\DayPartFactory;
use Xibo\Factory\DisplayFactory;
use Xibo\Factory\DisplayGroupFactory;
use Xibo\Factory\FolderFactory;
use Xibo\Factory\LayoutFactory;
use Xibo\Factory\MediaFactory;
use Xibo\Factory\ModuleFactory;
use Xibo\Factory\PermissionFactory;
use Xibo\Factory\PlayerVersionFactory;
use Xibo\Factory\PlaylistFactory;
use Xibo\Factory\RegionFactory;
use Xibo\Factory\ScheduleFactory;
use Xibo\Factory\TagFactory;
use Xibo\Factory\UserFactory;
use Xibo\Factory\UserGroupFactory;
use Xibo\Factory\WidgetFactory;
use Xibo\Helper\ByteFormatter;
use Xibo\Helper\DateFormatHelper;
use Xibo\Helper\Environment;
use Xibo\Helper\HttpCacheProvider;
use Xibo\Helper\Random;
use Xibo\Helper\SanitizerService;
use Xibo\Helper\XiboUploadHandler;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Storage\StorageServiceInterface;
use Xibo\Support\Exception\AccessDeniedException;
use Xibo\Support\Exception\ConfigurationException;
use Xibo\Support\Exception\GeneralException;
use Xibo\Support\Exception\InvalidArgumentException;
use Xibo\Support\Exception\LibraryFullException;
use Xibo\Support\Exception\NotFoundException;

/**
 * Class Library
 * @package Xibo\Controller
 */
class Library extends Base
{
    /**
     * @var StorageServiceInterface
     */
    private $store;

    /** @var  PoolInterface */
    private $pool;

    /** @var EventDispatcherInterface */
    private $dispatcher;

    /**
     * @var UserFactory
     */
    private $userFactory;

    /**
     * @var ModuleFactory
     */
    private $moduleFactory;

    /**
     * @var TagFactory
     */
    private $tagFactory;

    /**
     * @var MediaFactory
     */
    private $mediaFactory;

    /**
     * @var WidgetFactory
     */
    private $widgetFactory;

    /** @var PlayerVersionFactory */
    private $playerVersionFactory;

    /**
     * @var PlaylistFactory
     */
    private $playlistFactory;

    /**
     * @var LayoutFactory
     */
    private $layoutFactory;

    /**
     * @var PermissionFactory
     */
    private $permissionFactory;

    /**
     * @var UserGroupFactory
     */
    private $userGroupFactory;

    /** @var  DisplayGroupFactory */
    private $displayGroupFactory;

    /** @var  RegionFactory */
    private $regionFactory;

    /** @var  DataSetFactory */
    private $dataSetFactory;

    /** @var  DisplayFactory */
    private $displayFactory;

    /** @var ScheduleFactory  */
    private $scheduleFactory;

    /** @var  DayPartFactory */
    private $dayPartFactory;

    /** @var HttpCacheProvider */
    private $cacheProvider;

    /** @var FolderFactory */
    private $folderFactory;

    /**
     * Set common dependencies.
     * @param LogServiceInterface $log
     * @param SanitizerService $sanitizerService
     * @param \Xibo\Helper\ApplicationState $state
     * @param \Xibo\Entity\User $user
     * @param \Xibo\Service\HelpServiceInterface $help
     * @param ConfigServiceInterface $config
     * @param StorageServiceInterface $store
     * @param PoolInterface $pool
     * @param EventDispatcherInterface $dispatcher
     * @param UserFactory $userFactory
     * @param ModuleFactory $moduleFactory
     * @param TagFactory $tagFactory
     * @param MediaFactory $mediaFactory
     * @param WidgetFactory $widgetFactory
     * @param PermissionFactory $permissionFactory
     * @param LayoutFactory $layoutFactory
     * @param PlaylistFactory $playlistFactory
     * @param UserGroupFactory $userGroupFactory
     * @param DisplayGroupFactory $displayGroupFactory
     * @param RegionFactory $regionFactory
     * @param DataSetFactory $dataSetFactory
     * @param DisplayFactory $displayFactory
     * @param ScheduleFactory $scheduleFactory
     * @param DayPartFactory $dayPartFactory
     * @param PlayerVersionFactory $playerVersionFactory
     * @param Twig $view
     * @param HttpCacheProvider $cacheProvider
     * @param FolderFactory $folderFactory
     */
    public function __construct($log, $sanitizerService, $state, $user, $help, $config, $store, $pool, $dispatcher, $userFactory, $moduleFactory, $tagFactory, $mediaFactory, $widgetFactory, $permissionFactory, $layoutFactory, $playlistFactory, $userGroupFactory, $displayGroupFactory, $regionFactory, $dataSetFactory, $displayFactory, $scheduleFactory, $dayPartFactory, $playerVersionFactory, $view, HttpCacheProvider $cacheProvider, $folderFactory)
    {
        $this->setCommonDependencies($log, $sanitizerService, $state, $user, $help, $config, $view);

        $this->store = $store;
        $this->moduleFactory = $moduleFactory;
        $this->mediaFactory = $mediaFactory;
        $this->widgetFactory = $widgetFactory;
        $this->pool = $pool;
        $this->dispatcher = $dispatcher;
        $this->userFactory = $userFactory;
        $this->tagFactory = $tagFactory;
        $this->permissionFactory = $permissionFactory;
        $this->layoutFactory = $layoutFactory;
        $this->playlistFactory = $playlistFactory;
        $this->userGroupFactory = $userGroupFactory;
        $this->displayGroupFactory = $displayGroupFactory;
        $this->regionFactory = $regionFactory;
        $this->dataSetFactory = $dataSetFactory;
        $this->displayFactory = $displayFactory;
        $this->scheduleFactory = $scheduleFactory;
        $this->dayPartFactory = $dayPartFactory;
        $this->playerVersionFactory = $playerVersionFactory;
        $this->cacheProvider = $cacheProvider;
        $this->folderFactory = $folderFactory;
    }

    /**
     * Get Dispatcher
     * @return EventDispatcherInterface
     */
    public function getDispatcher()
    {
        return $this->dispatcher;
    }

    /**
     * Get Module Factory
     * @return ModuleFactory
     */
    public function getModuleFactory()
    {
        return $this->moduleFactory;
    }

    /**
     * Get Media Factory
     * @return MediaFactory
     */
    public function getMediaFactory()
    {
        return $this->mediaFactory;
    }

    /**
     * Get Permission Factory
     * @return PermissionFactory
     */
    public function getPermissionFactory()
    {
        return $this->permissionFactory;
    }

    /**
     * Get Widget Factory
     * @return WidgetFactory
     */
    public function getWidgetFactory()
    {
        return $this->widgetFactory;
    }

    /**
     * Get Layout Factory
     * @return LayoutFactory
     */
    public function getLayoutFactory()
    {
        return $this->layoutFactory;
    }

    /**
     * Get Playlist Factory
     * @return PlaylistFactory
     */
    public function getPlaylistFactory()
    {
        return $this->playlistFactory;
    }

    /**
     * Get PlayerVersion Factory
     * @return PlayerVersionFactory
     */
    public function getPlayerVersionFactory()
    {
        return $this->playerVersionFactory;
    }

    /**
     * Get UserGroup Factory
     * @return UserGroupFactory
     */
    public function getUserGroupFactory()
    {
        return $this->userGroupFactory;
    }

    /**
     * Get RegionFactory
     * @return RegionFactory
     */
    public function getRegionFactory()
    {
        return $this->regionFactory;
    }

    /**
     * Get DisplayGroup Factory
     * @return DisplayGroupFactory
     */
    public function getDisplayGroupFactory()
    {
        return $this->displayGroupFactory;
    }

    /**
     * @return DataSetFactory
     */
    public function getDataSetFactory()
    {
        return $this->dataSetFactory;
    }

    /**
     * @return DisplayFactory
     */
    public function getDisplayFactory()
    {
        return $this->displayFactory;
    }

    /**
     * @return ScheduleFactory
     */
    public function getScheduleFactory()
    {
        return $this->scheduleFactory;
    }

    /**
     * @return TagFactory
     */
    public function getTagFactory()
    {
        return $this->tagFactory;
    }

    /**
     * @return FolderFactory
     */
    public function getFolderFactory()
    {
        return $this->folderFactory;
    }

    /**
     * Displays the page logic
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws GeneralException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    function displayPage(Request $request, Response $response)
    {
        // Users we have permission to see
        $this->getState()->template = 'library-page';
        $this->getState()->setData([
            'users' => $this->userFactory->query(),
            'modules' => $this->moduleFactory->query(['module'], ['regionSpecific' => 0, 'enabled' => 1, 'notPlayerSoftware' => 1, 'notSavedReport' => 1]),
            'groups' => $this->userGroupFactory->query(),
            'validExt' => implode('|', $this->moduleFactory->getValidExtensions(['notPlayerSoftware' => 1, 'notSavedReport' => 1]))
        ]);

        return $this->render($request,$response);
    }

    /**
     * Set Enable Stats Collection of a media
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws ConfigurationException
     * @throws GeneralException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\DuplicateEntityException
     * @SWG\Put(
     *  path="/library/setenablestat/{mediaId}",
     *  operationId="mediaSetEnableStat",
     *  tags={"library"},
     *  summary="Enable Stats Collection",
     *  description="Set Enable Stats Collection? to use for the collection of Proof of Play statistics for a media.",
     *  @SWG\Parameter(
     *      name="mediaId",
     *      in="path",
     *      description="The Media ID",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="enableStat",
     *      in="formData",
     *      description="The option to enable the collection of Media Proof of Play statistics",
     *      type="string",
     *      required=true
     *   ),
     *  @SWG\Response(
     *      response=204,
     *      description="successful operation"
     *  )
     * )
     */
    public function setEnableStat(Request $request, Response $response, $id)
    {
        // Get the Media
        $media = $this->mediaFactory->getById($id);

        // Check Permissions
        if (!$this->getUser()->checkViewable($media)) {
            throw new AccessDeniedException();
        }

        $enableStat = $this->getSanitizer($request->getParams())->getString('enableStat');

        $media->enableStat = $enableStat;
        $media->save(['saveTags' => false]);

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 204,
            'message' => sprintf(__('For Media %s Enable Stats Collection is set to %s'), $media->name, __($media->enableStat))
        ]);

        return $this->render($request, $response);
    }

    /**
     * Set Enable Stat Form
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function setEnableStatForm(Request $request, Response $response, $id)
    {
        // Get the Media
        $media = $this->mediaFactory->getById($id);

        // Check Permissions
        if (!$this->getUser()->checkViewable($media)) {
            throw new AccessDeniedException();
        }

        $data = [
            'media' => $media,
            'help' => $this->getHelp()->link('Layout', 'EnableStat')
        ];

        $this->getState()->template = 'library-form-setenablestat';
        $this->getState()->setData($data);

        return $this->render($request, $response);
    }

    /**
     * Prints out a Table of all media items
     *
     * @SWG\Get(
     *  path="/library",
     *  operationId="librarySearch",
     *  tags={"library"},
     *  summary="Library Search",
     *  description="Search the Library for this user",
     *  @SWG\Parameter(
     *      name="mediaId",
     *      in="query",
     *      description="Filter by Media Id",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="media",
     *      in="query",
     *      description="Filter by Media Name",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="type",
     *      in="query",
     *      description="Filter by Media Type",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="ownerId",
     *      in="query",
     *      description="Filter by Owner Id",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="retired",
     *      in="query",
     *      description="Filter by Retired",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="tags",
     *      in="query",
     *      description="Filter by Tags - comma seperated",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="exactTags",
     *      in="query",
     *      description="A flag indicating whether to treat the tags filter as an exact match",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="duration",
     *      in="query",
     *      description="Filter by Duration - a number or less-than,greater-than,less-than-equal or great-than-equal followed by a | followed by a number",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="fileSize",
     *      in="query",
     *      description="Filter by File Size - a number or less-than,greater-than,less-than-equal or great-than-equal followed by a | followed by a number",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="ownerUserGroupId",
     *      in="query",
     *      description="Filter by users in this UserGroupId",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="folderId",
     *      in="query",
     *      description="Filter by Folder ID",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Response(
     *      response=200,
     *      description="successful operation",
     *      @SWG\Schema(
     *          type="array",
     *          @SWG\Items(ref="#/definitions/Media")
     *      )
     *  )
     * )
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws GeneralException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    function grid(Request $request, Response $response)
    {
        $user = $this->getUser();

        $parsedQueryParams = $this->getSanitizer($request->getQueryParams());

        // Construct the SQL
        $mediaList = $this->mediaFactory->query($this->gridRenderSort($request), $this->gridRenderFilter([
            'mediaId' => $parsedQueryParams->getInt('mediaId'),
            'name' => $parsedQueryParams->getString('media'),
            'useRegexForName' => $parsedQueryParams->getCheckbox('useRegexForName'),
            'nameExact' => $parsedQueryParams->getString('nameExact'),
            'type' => $parsedQueryParams->getString('type'),
            'tags' => $parsedQueryParams->getString('tags'),
            'exactTags' => $parsedQueryParams->getCheckbox('exactTags'),
            'ownerId' => $parsedQueryParams->getInt('ownerId'),
            'retired' => $parsedQueryParams->getInt('retired'),
            'duration' => $parsedQueryParams->getInt('duration'),
            'fileSize' => $parsedQueryParams->getString('fileSize'),
            'ownerUserGroupId' => $parsedQueryParams->getInt('ownerUserGroupId'),
            'assignable' => $parsedQueryParams->getInt('assignable'),
            'folderId' => $parsedQueryParams->getInt('folderId'),
            'notPlayerSoftware' => 1,
            'notSavedReport' => 1
        ], $request));

        // Add some additional row content
        foreach ($mediaList as $media) {
            /* @var \Xibo\Entity\Media $media */
            $media->revised = ($media->parentId != 0) ? 1 : 0;

            // Thumbnail URL
            $media->thumbnail = '';
            $media->thumbnailUrl = '';
            $media->downloadUrl = '';

            $module = $this->moduleFactory->createWithMedia($media);

            if ($module->hasThumbnail()) {
                $download = $this->urlFor($request,'library.download', ['id' => $media->mediaId], ['preview' => 1]);
                $media->thumbnail = '<a class="img-replace" data-toggle="lightbox" data-type="image" href="' . $download . '"><img src="' . $download . '&width=100&height=56&cache=1" /></i></a>';
                $media->thumbnailUrl = $download . '&width=100&height=56&cache=1';
                $media->downloadUrl = $download;
            }

            $media->fileSizeFormatted = ByteFormatter::format($media->fileSize);

            // Media expiry
            $media->mediaExpiresIn = __('Expires %s');
            $media->mediaExpiryFailed = __('Expired ');
            $media->mediaNoExpiryDate = __('Never');

            if ($this->isApi($request)) {
                $media->excludeProperty('mediaExpiresIn');
                $media->excludeProperty('mediaExpiryFailed');
                $media->excludeProperty('mediaNoExpiryDate');
                $media->expires = ($media->expires == 0) ? 0 : Carbon::createFromTimestamp($media->expires)->format(DateFormatHelper::getSystemFormat());
                continue;
            }

            $media->includeProperty('buttons');

            switch ($media->released) {

                case 1:
                    $media->releasedDescription = '';
                    break;

                case 2:
                    $media->releasedDescription = __('The uploaded image is too large and cannot be processed, please use another image.');
                    break;

                default:
                    $media->releasedDescription = __('This image will be resized according to set thresholds and limits.');
            }

            switch ($media->enableStat) {

                case 'On':
                    $media->enableStatDescription = __('This Media has enable stat collection set to ON');
                    break;

                case 'Off':
                    $media->enableStatDescription = __('This Media has enable stat collection set to OFF');
                    break;

                default:
                    $media->enableStatDescription = __('This Media has enable stat collection set to INHERIT');
            }

            $media->buttons = [];

            // Buttons
            if ($this->getUser()->featureEnabled('library.modify')
                && $user->checkEditable($media)
            ) {
                // Edit
                $media->buttons[] = array(
                    'id' => 'content_button_edit',
                    'url' => $this->urlFor($request,'library.edit.form', ['id' => $media->mediaId]),
                    'text' => __('Edit')
                );

                // Copy Button
                $media->buttons[] = array(
                    'id' => 'media_button_copy',
                    'url' => $this->urlFor($request,'library.copy.form', ['id' => $media->mediaId]),
                    'text' => __('Copy')
                );

                // Select Folder
                $media->buttons[] = [
                    'id' => 'library_button_selectfolder',
                    'url' => $this->urlFor($request,'library.selectfolder.form', ['id' => $media->mediaId]),
                    'text' => __('Select Folder'),
                    'multi-select' => true,
                    'dataAttributes' => [
                        ['name' => 'commit-url', 'value' => $this->urlFor($request,'library.selectfolder', ['id' => $media->mediaId])],
                        ['name' => 'commit-method', 'value' => 'put'],
                        ['name' => 'id', 'value' => 'library_button_selectfolder'],
                        ['name' => 'text', 'value' => __('Move to Folder')],
                        ['name' => 'rowtitle', 'value' => $media->name],
                        ['name' => 'form-callback', 'value' => 'moveFolderMultiSelectFormOpen']
                    ]
                ];
            }

            if ($this->getUser()->featureEnabled('library.modify')
                && $user->checkDeleteable($media)
            ) {
                // Delete Button
                $media->buttons[] = [
                    'id' => 'content_button_delete',
                    'url' => $this->urlFor($request,'library.delete.form', ['id' => $media->mediaId]),
                    'text' => __('Delete'),
                    'multi-select' => true,
                    'dataAttributes' => [
                        ['name' => 'commit-url', 'value' => $this->urlFor($request,'library.delete', ['id' => $media->mediaId])],
                        ['name' => 'commit-method', 'value' => 'delete'],
                        ['name' => 'id', 'value' => 'content_button_delete'],
                        ['name' => 'text', 'value' => __('Delete')],
                        ['name' => 'sort-group', 'value' => 1],
                        ['name' => 'rowtitle', 'value' => $media->name],
                        ['name' => 'form-callback', 'value' => 'setDefaultMultiSelectFormOpen']
                    ]
                ];
            }

            if ($this->getUser()->featureEnabled('library.modify')
                && $user->checkPermissionsModifyable($media)
            ) {
                // Permissions
                $media->buttons[] = [
                    'id' => 'content_button_permissions',
                    'url' => $this->urlFor($request,'user.permissions.form', ['entity' => 'Media', 'id' => $media->mediaId]),
                    'text' => __('Permissions'),
                    'dataAttributes' => [
                        ['name' => 'commit-url', 'value' => $this->urlFor($request,'user.permissions.multi', ['entity' => 'Media', 'id' => $media->mediaId])],
                        ['name' => 'commit-method', 'value' => 'post'],
                        ['name' => 'id', 'value' => 'content_button_permissions'],
                        ['name' => 'text', 'value' => __('Permissions')],
                        ['name' => 'rowtitle', 'value' => $media->name],
                        ['name' => 'sort-group', 'value' => 2],
                        ['name' => 'custom-handler', 'value' => 'XiboMultiSelectPermissionsFormOpen'],
                        ['name' => 'custom-handler-url', 'value' => $this->urlFor($request,'user.permissions.multi.form', ['entity' => 'Media'])],
                        ['name' => 'content-id-name', 'value' => 'mediaId']
                    ]
                ];
            }

            // Download
            // No feature permissions here, anyone can get a file based on sharing.
            $media->buttons[] = ['divider' => true];
            $media->buttons[] = array(
                'id' => 'content_button_download',
                'linkType' => '_self', 'external' => true,
                'url' => $this->urlFor($request,'library.download', ['id' => $media->mediaId]) . '?attachment=' . $media->fileName,
                'text' => __('Download')
            );

            // Set Enable Stat
            if ($this->getUser()->featureEnabled('library.modify')
                && $this->getUser()->checkEditable($media)
            ) {
                $media->buttons[] = ['divider' => true];

                $media->buttons[] = array(
                    'id' => 'library_button_setenablestat',
                    'url' => $this->urlFor($request,'library.setenablestat.form', ['id' => $media->mediaId]),
                    'text' => __('Enable stats collection?'),
                    'multi-select' => true,
                    'dataAttributes' => array(
                        array('name' => 'commit-url', 'value' => $this->urlFor($request,'library.setenablestat', ['id' => $media->mediaId])),
                        array('name' => 'commit-method', 'value' => 'put'),
                        array('name' => 'id', 'value' => 'library_button_setenablestat'),
                        array('name' => 'text', 'value' => __('Enable stats collection?')),
                        array('name' => 'rowtitle', 'value' => $media->name),
                        ['name' => 'form-callback', 'value' => 'setEnableStatMultiSelectFormOpen']
                    )
                );
            }

            if ($this->getUser()->featureEnabled(['schedule.view', 'layout.view'])) {
                $media->buttons[] = ['divider' => true];

                $media->buttons[] = array(
                    'id' => 'usage_report_button',
                    'url' => $this->urlFor($request, 'library.usage.form', ['id' => $media->mediaId]),
                    'text' => __('Usage Report')
                );
            }
        }

        $this->getState()->template = 'grid';
        $this->getState()->recordsTotal = $this->mediaFactory->countLast();
        $this->getState()->setData($mediaList);

        return $this->render($request, $response);
    }

    /**
     * Media Delete Form
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function deleteForm(Request $request, Response $response, $id)
    {
        $media = $this->mediaFactory->getById($id);

        if (!$this->getUser()->checkDeleteable($media)) {
            throw new AccessDeniedException();
        }

        $media->setChildObjectDependencies($this->layoutFactory, $this->widgetFactory, $this->displayGroupFactory, $this->displayFactory, $this->scheduleFactory, $this->playerVersionFactory);
        $media->load(['deleting' => true]);

        $this->getState()->template = 'library-form-delete';
        $this->getState()->setData([
            'media' => $media,
            'help' => $this->getHelp()->link('Library', 'Delete')
        ]);

        return $this->render($request, $response);
    }

    /**
     * Delete Media
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws ConfigurationException
     * @throws GeneralException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\DuplicateEntityException
     * @SWG\Delete(
     *  path="/library/{mediaId}",
     *  operationId="libraryDelete",
     *  tags={"library"},
     *  summary="Delete Media",
     *  description="Delete Media from the Library",
     *  @SWG\Parameter(
     *      name="mediaId",
     *      in="path",
     *      description="The Media ID to Delete",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="forceDelete",
     *      in="formData",
     *      description="If the media item has been used should it be force removed from items that uses it?",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Response(
     *      response=204,
     *      description="successful operation"
     *  )
     * )
     */
    public function delete(Request $request, Response $response, $id)
    {
        $media = $this->mediaFactory->getById($id);

        if (!$this->getUser()->checkDeleteable($media)) {
            throw new AccessDeniedException();
        }

        // Check
        $media->setChildObjectDependencies($this->layoutFactory, $this->widgetFactory, $this->displayGroupFactory, $this->displayFactory, $this->scheduleFactory, $this->playerVersionFactory);
        $media->load(['deleting' => true]);

        if ($media->isUsed() && $this->getSanitizer($request->getParams())->getCheckbox('forceDelete') == 0) {
            throw new InvalidArgumentException(__('This library item is in use.'));
        }

        // Delete
        $media->delete();

        // Do we need to reassess fonts?
        if ($media->mediaType == 'font') {
            $this->installFonts();
        }

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 204,
            'message' => sprintf(__('Deleted %s'), $media->name)
        ]);

        return $this->render($request, $response);
    }

    /**
     * Add a file to the library
     *  expects to be fed by the blueimp file upload handler
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws ConfigurationException
     * @throws GeneralException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @SWG\Post(
     *  path="/library",
     *  operationId="libraryAdd",
     *  tags={"library"},
     *  summary="Add Media",
     *  description="Add Media to the Library",
     *  @SWG\Parameter(
     *      name="files",
     *      in="formData",
     *      description="The Uploaded File",
     *      type="file",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="name",
     *      in="formData",
     *      description="Optional Media Name",
     *      type="string",
     *      required=false
     *  ),
     *  @SWG\Parameter(
     *      name="oldMediaId",
     *      in="formData",
     *      description="Id of an existing media file which should be replaced with the new upload",
     *      type="integer",
     *      required=false
     *  ),
     *  @SWG\Parameter(
     *      name="updateInLayouts",
     *      in="formData",
     *      description="Flag (0, 1), set to 1 to update this media in all layouts (use with oldMediaId) ",
     *      type="integer",
     *      required=false
     *  ),
     *  @SWG\Parameter(
     *      name="deleteOldRevisions",
     *      in="formData",
     *      description="Flag (0 , 1), to either remove or leave the old file revisions (use with oldMediaId)",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="tags",
     *      in="formData",
     *      description="Comma separated string of Tags that should be assigned to uploaded Media",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="expires",
     *      in="formData",
     *      description="Date in Y-m-d H:i:s format, will set expiration date on the uploaded Media",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="folderId",
     *      in="formData",
     *      description="Folder ID to which this object should be assigned to",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Response(
     *      response=200,
     *      description="successful operation"
     *  )
     * )
     */
    public function add(Request $request, Response $response)
    {
        $parsedBody = $this->getSanitizer($request->getParams());
        $options = $parsedBody->getArray('options', ['default' => []]);
        $oldFolderId = 1;

        $options = array_merge([
            'oldMediaId' => null,
            'updateInLayouts' => 0,
            'deleteOldRevisions' => 0,
            'allowMediaTypeChange' => 0
        ], $options);

        $libraryFolder = $this->getConfig()->getSetting('LIBRARY_LOCATION');

        if ($parsedBody->getDate('expires') != null ) {

            if ($parsedBody->getDate('expires')->format('U') > Carbon::now()->format('U')) {
                $expires = $parsedBody->getDate('expires')->format('U');
            } else {
                throw new InvalidArgumentException(__('Cannot set Expiry date in the past'), 'expires');
            }
        } else {
            $expires = 0;
        }

        // Make sure the library exists
        self::ensureLibraryExists($libraryFolder);

        // Get Valid Extensions
        if ($parsedBody->getInt('oldMediaId') !== null) {
            $media = $this->mediaFactory->getById($parsedBody->getInt('oldMediaId'));
            $oldFolderId = $media->folderId;
            $validExt = $this->moduleFactory->getValidExtensions(['type' => $media->mediaType]);
        }
        else {
            $validExt = $this->moduleFactory->getValidExtensions();
        }

        // Make sure there is room in the library
        $libraryLimit = $this->getConfig()->getSetting('LIBRARY_SIZE_LIMIT_KB') * 1024;

        $options = array(
            'userId' => $this->getUser()->userId,
            'controller' => $this,
            'oldMediaId' => $parsedBody->getInt('oldMediaId', ['default' => $options['oldMediaId']]),
            'widgetId' => $parsedBody->getInt('widgetId'),
            'updateInLayouts' => $parsedBody->getCheckbox('updateInLayouts'),
            'deleteOldRevisions' => $parsedBody->getCheckbox('deleteOldRevisions'),
            'allowMediaTypeChange' => $options['allowMediaTypeChange'],
            'displayOrder' => $parsedBody->getInt('displayOrder'),
            'playlistId' => $parsedBody->getInt('playlistId'),
            'upload_dir' => $libraryFolder . 'temp/',
            'download_via_php' => true,
            'script_url' => $this->urlFor($request,'library.add'),
            'upload_url' => $this->urlFor($request,'library.add'),
            'image_versions' => array(),
            'accept_file_types' => '/\.' . implode('|', $validExt) . '$/i',
            'libraryLimit' => $libraryLimit,
            'libraryQuotaFull' => ($libraryLimit > 0 && $this->libraryUsage() > $libraryLimit),
            'expires' => $expires,
            'oldFolderId' => $parsedBody->getInt('folderId', ['default' => $oldFolderId])
        );

        // Output handled by UploadHandler
        $this->setNoOutput(true);

        $this->getLog()->debug('Hand off to Upload Handler with options: ' . json_encode($options));

        // Hand off to the Upload Handler provided by jquery-file-upload
        new XiboUploadHandler($options);

        return $this->render($request, $response);
    }

    /**
     * Edit Form
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function editForm(Request $request, Response $response, $id)
    {
        $media = $this->mediaFactory->getById($id);

        if (!$this->getUser()->checkEditable($media)) {
            throw new AccessDeniedException();
        }

        $media->enableStat = ($media->enableStat == null) ? $this->getConfig()->getSetting('MEDIA_STATS_ENABLED_DEFAULT') : $media->enableStat;

        $this->getState()->template = 'library-form-edit';
        $this->getState()->setData([
            'media' => $media,
            'validExtensions' => implode('|', $this->moduleFactory->getValidExtensions(['type' => $media->mediaType])),
            'help' => $this->getHelp()->link('Library', 'Edit'),
            'tags' => $this->tagFactory->getTagsWithValues($media),
            'expiryDate' => ($media->expires == 0 ) ? null : Carbon::createFromTimestamp($media->expires)->format(DateFormatHelper::getSystemFormat(), $media->expires)
        ]);

        return $this->render($request, $response);
    }

    /**
     * Edit Media
     *
     * @SWG\Put(
     *  path="/library/{mediaId}",
     *  operationId="libraryEdit",
     *  tags={"library"},
     *  summary="Edit Media",
     *  description="Edit a Media Item in the Library",
     *  @SWG\Parameter(
     *      name="mediaId",
     *      in="path",
     *      description="The Media ID to Edit",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="name",
     *      in="formData",
     *      description="Media Item Name",
     *      type="string",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="duration",
     *      in="formData",
     *      description="The duration in seconds for this Media Item",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="retired",
     *      in="formData",
     *      description="Flag indicating if this media is retired",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="tags",
     *      in="formData",
     *      description="Comma separated list of Tags",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="updateInLayouts",
     *      in="formData",
     *      description="Flag indicating whether to update the duration in all Layouts the Media is assigned to",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="expires",
     *      in="formData",
     *      description="Date in Y-m-d H:i:s format, will set expiration date on the Media item",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="folderId",
     *      in="formData",
     *      description="Folder ID to which this media should be assigned to",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Response(
     *      response=200,
     *      description="successful operation",
     *      @SWG\Schema(ref="#/definitions/Media")
     *  )
     * )
     *
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws ConfigurationException
     * @throws GeneralException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\DuplicateEntityException
     */
    public function edit(Request $request, Response $response, $id)
    {
        $media = $this->mediaFactory->getById($id);
        $sanitizedParams = $this->getSanitizer($request->getParams());

        if (!$this->getUser()->checkEditable($media)) {
            throw new AccessDeniedException();
        }

        if ($media->mediaType == 'font') {
            throw new InvalidArgumentException(__('Sorry, Fonts do not have any editable properties.'));
        }

        $media->name = $sanitizedParams->getString('name');
        $media->duration = $sanitizedParams->getInt('duration');
        $media->retired = $sanitizedParams->getCheckbox('retired');

        if ($this->getUser()->featureEnabled('tag.tagging')) {
            $media->replaceTags($this->tagFactory->tagsFromString($sanitizedParams->getString('tags')));
        }

        $media->enableStat = $sanitizedParams->getString('enableStat');
        $media->folderId = $sanitizedParams->getInt('folderId', ['default' => $media->folderId]);

        if ($sanitizedParams->getDate('expires') != null ) {

            if ($sanitizedParams->getDate('expires')->format('U') > Carbon::now()->format('U')) {
                $media->expires = $sanitizedParams->getDate('expires')->format('U');
            } else {
                throw new InvalidArgumentException(__('Cannot set Expiry date in the past'), 'expires');
            }
        } else {
            $media->expires = 0;
        }

        // Should we update the media in all layouts?
        if ($sanitizedParams->getCheckbox('updateInLayouts') == 1 || $media->hasPropertyChanged('enableStat')) {
            foreach ($this->widgetFactory->getByMediaId($media->mediaId) as $widget) {
                /* @var Widget $widget */
                $widget->duration = $media->duration;
                $widget->save();
            }
        }

        $media->save();

        // Are we a font
        if ($media->mediaType == 'font') {
            // We may have made changes and need to regenerate
            $this->installFonts();
        }

        // Return
        $this->getState()->hydrate([
            'message' => sprintf(__('Edited %s'), $media->name),
            'id' => $media->mediaId,
            'data' => $media
        ]);

        return $this->render($request, $response);
    }

    /**
     * Tidy Library
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws ConfigurationException
     * @throws GeneralException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function tidyForm(Request $request, Response $response)
    {
        if ($this->getConfig()->getSetting('SETTING_LIBRARY_TIDY_ENABLED') != 1) {
            throw new ConfigurationException(__('Sorry this function is disabled.'));
        }

        // Work out how many files there are
        $media = $this->mediaFactory->query(null, ['unusedOnly' => 1, 'ownerId' => $this->getUser()->userId]);

        $sumExcludingGeneric = 0;
        $countExcludingGeneric = 0;
        $sumGeneric = 0;
        $countGeneric = 0;

        foreach ($media as $item) {
            if ($item->mediaType == 'genericfile') {
                $countGeneric++;
                $sumGeneric = $sumGeneric + $item->fileSize;
            }
            else {
                $countExcludingGeneric++;
                $sumExcludingGeneric = $sumExcludingGeneric + $item->fileSize;
            }
        }

        $this->getState()->template = 'library-form-tidy';
        $this->getState()->setData([
            'sumExcludingGeneric' => ByteFormatter::format($sumExcludingGeneric),
            'sumGeneric' => ByteFormatter::format($sumGeneric),
            'countExcludingGeneric' => $countExcludingGeneric,
            'countGeneric' => $countGeneric,
            'help' => $this->getHelp()->link('Content', 'TidyLibrary')
        ]);

        return $this->render($request, $response);
    }

    /**
     * Tidies up the library
     *
     * @SWG\Delete(
     *  path="/library/tidy",
     *  operationId="libraryTidy",
     *  tags={"library"},
     *  summary="Tidy Library",
     *  description="Routine tidy of the library, removing unused files.",
     *  @SWG\Parameter(
     *      name="tidyGenericFiles",
     *      in="formData",
     *      description="Also delete generic files?",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Response(
     *      response=200,
     *      description="successful operation"
     *  )
     * )
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws ConfigurationException
     * @throws GeneralException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function tidy(Request $request, Response $response)
    {
        if ($this->getConfig()->getSetting('SETTING_LIBRARY_TIDY_ENABLED') != 1) {
            throw new ConfigurationException(__('Sorry this function is disabled.'));
        }

        $tidyGenericFiles = $this->getSanitizer($request->getParams())->getCheckbox('tidyGenericFiles');

        // Get a list of media that is not in use (for this user)
        $media = $this->mediaFactory->query(null, ['unusedOnly' => 1, 'ownerId' => $this->getUser()->userId]);

        $i = 0;
        foreach ($media as $item) {
            /* @var Media $item */
            if ($tidyGenericFiles != 1 && $item->mediaType == 'genericfile')
                continue;

            // Eligable for delete
            $i++;
            $item->setChildObjectDependencies($this->layoutFactory, $this->widgetFactory, $this->displayGroupFactory, $this->displayFactory, $this->scheduleFactory, $this->playerVersionFactory);
            $item->load();
            $item->delete();
        }

        // Return
        $this->getState()->hydrate([
            'message' => __('Library Tidy Complete'),
            'countDeleted' => $i
        ]);

        return $this->render($request, $response);
    }

    /**
     * Make sure the library exists
     * @param string $libraryFolder
     * @throws ConfigurationException when the library is not writable
     */
    public static function ensureLibraryExists($libraryFolder)
    {
        // Check that this location exists - and if not create it..
        if (!file_exists($libraryFolder)) {
            mkdir($libraryFolder, 0777, true);
        }

        if (!file_exists($libraryFolder . '/temp')) {
            mkdir($libraryFolder . '/temp', 0777, true);
        }
        if (!file_exists($libraryFolder . '/cache')) {
            mkdir($libraryFolder . '/cache', 0777, true);
        }

        if (!file_exists($libraryFolder . '/screenshots')) {
            mkdir($libraryFolder . '/screenshots', 0777, true);
        }

        if (!file_exists($libraryFolder . '/attachment')) {
            mkdir($libraryFolder . '/attachment', 0777, true);
        }

        // Check that we are now writable - if not then error
        if (!is_writable($libraryFolder)) {
            throw new ConfigurationException(__('Library not writable'));
        }
    }

    /**
     * @return string
     */
    public function getLibraryCacheUri()
    {
        return $this->getConfig()->getSetting('LIBRARY_LOCATION') . '/cache';
    }

    /**
     * Library Usage
     * @return int
     */
    public function libraryUsage()
    {
        $results = $this->store->select('SELECT IFNULL(SUM(FileSize), 0) AS SumSize FROM media', array());

        return $this->getSanitizer($results[0])->getInt('SumSize');
    }

    /**
     * @SWG\Get(
     *  path="/library/download/{mediaId}/{type}",
     *  operationId="libraryDownload",
     *  tags={"library"},
     *  summary="Download Media",
     *  description="Download a Media file from the Library",
     *  produces={"application/octet-stream"},
     *  @SWG\Parameter(
     *      name="mediaId",
     *      in="path",
     *      description="The Media ID to Download",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="type",
     *      in="path",
     *      description="The Module Type of the Download",
     *      type="string",
     *      required=true
     *   ),
     *  @SWG\Response(
     *      response=200,
     *      description="successful operation",
     *      @SWG\Schema(type="file"),
     *      @SWG\Header(
     *          header="X-Sendfile",
     *          description="Apache Send file header - if enabled.",
     *          type="string"
     *      ),
     *      @SWG\Header(
     *          header="X-Accel-Redirect",
     *          description="nginx send file header - if enabled.",
     *          type="string"
     *      )
     *  )
     * )
     *
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function download(Request $request, Response $response)
    {
        $routeContext = RouteContext::fromRequest($request);
        $route = $routeContext->getRoute();
        $mediaId = $route->getArgument('id');
        $type = $route->getArgument('type');

        // We can download by mediaId or by mediaName.
        if (is_numeric($mediaId)) {
            $media = $this->mediaFactory->getById($mediaId);
        } else {
            $media = $this->mediaFactory->getByName($mediaId);
        }

        $this->getLog()->debug('Download request for mediaId ' . $mediaId
            . ' and type ' . $type . '. Media is a '
            . $media->mediaType . ', is system file:' . $media->moduleSystemFile);

        if ($media->mediaType === 'module' && $media->moduleSystemFile === 1) {
            // grant permissions
            // (everyone has access to module system files)
        } else if ($media->mediaType === 'module') {
            // Make sure that our user has this mediaId assigned to a Widget they can view
            // we can't test for normal media permissions, because no user has direct access to these "module" files
            // https://github.com/xibosignage/xibo/issues/1304
            if (count($this->widgetFactory->query(null, ['mediaId' => $mediaId])) <= 0) {
                throw new AccessDeniedException();
            }
        } else if (!$this->getUser()->checkViewable($media)) {
            throw new AccessDeniedException();
        }

        if ($type == null && $media->mediaType === 'module') {
            $type = 'genericfile';
        }

        if ($type != '') {
            $widget = $this->moduleFactory->create($type);
            $widgetOverride = $this->widgetFactory->createEmpty();
            $widgetOverride->assignMedia($media->mediaId);
            $widget->setWidget($widgetOverride);
        } else {
            // Make a media module
            $this->getLog()->debug('Creating a module with Media: ' . $media->mediaId);

            $widget = $this->moduleFactory->createWithMedia($media);
        }

        if ($widget->getModule()->regionSpecific == 1) {
            throw new NotFoundException(__('Cannot download region specific module'));
        }

        $this->getLog()->debug('About to call download for Widget: ' . $widget->getModuleType());
        $response = $widget->download($request, $response);

        $this->setNoOutput(true);

        return $this->render($request, $response);
    }

    /**
     * Return the CMS flavored font css
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface
     * @throws ConfigurationException
     * @throws GeneralException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\DuplicateEntityException
     */
    public function fontCss(Request $request, Response $response)
    {
        // Regenerate the CSS for fonts
        $css = $this->installFonts(['invalidateCache' => false], $request);

        // Work out the etag
        /** @var $httpCache HttpCacheProvider*/
        $httpCache = $this->cacheProvider;
        // Issue some headers
        $response = $httpCache->withEtag($response, md5($css['css']));
        $tempFileName = $this->getConfig()->getSetting('LIBRARY_LOCATION') . 'temp/fontcss_' . Random::generateString();
        // Return the CSS to the browser as a file
        $out = fopen($tempFileName, 'w');
        fputs($out, $css['css']);
        fclose($out);

        $this->setNoOutput(true);

        $response = $response->withHeader('Content-Type', 'text/css')
                             ->withBody(new Stream(fopen($tempFileName, 'r')));

        return $this->render($request, $response);
    }

    /**
     * Return the CMS flavored font css
     * @param Request|null $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws ConfigurationException
     * @throws GeneralException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\DuplicateEntityException
     */
    public function fontList(Request $request, Response $response)
    {
        // Regenerate the CSS for fonts
        $css = $this->installFonts(['invalidateCache' => false], $request);

        // Return
        $this->getState()->hydrate([
            'data' => $css['list']
        ]);

        return $this->render($request, $response);
    }

    /**
     * Get font CKEditor config
     * @param Request|null $request
     * @return string
     * @throws ConfigurationException
     * @throws GeneralException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\DuplicateEntityException
     */
    public function fontCKEditorConfig(Request $request = null)
    {
        // Regenerate the CSS for fonts
        $css = $this->installFonts(['invalidateCache' => false], $request);

        return $css['ckeditor'];
    }

    /**
     * Installs fonts
     * @param array $options
     * @param Request|null $request
     * @return array
     * @throws ConfigurationException
     * @throws GeneralException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\DuplicateEntityException
     */
    public function installFonts($options = [], Request $request = null)
    {
        $options = array_merge([
            'invalidateCache' => true
        ], $options);

        $this->getLog()->debug('Install Fonts called with options: ' . json_encode($options));

        // Drop the entire font cache as we cannot selectively tell whether the change that caused
        // this effects all users or not.
        // Important to note, that we aren't regenerating each user at this point in time, we're only clearing the cache
        // for them all and generating the current user.
        // We then make sure that subsequent generates do not change the library fonts.css
        if ($options['invalidateCache']) {
            $this->getLog()->debug('Dropping font cache and regenerating.');
            $this->pool->deleteItem('fontCss/');
        }

        // Each user has their own font cache (due to permissions) and the displays have their own font cache too
        // Get the item from the cache
        $cssItem = $this->pool->getItem('fontCss/' . $this->getUser()->userId);
        $cssItem->setInvalidationMethod(Invalidation::SLEEP, 5000, 15);

        // Get the CSS
        $cssDetails = $cssItem->get();

        if ($options['invalidateCache'] || $cssItem->isMiss()) {
            $this->getLog()->debug('Regenerating font cache');

            // lock the cache
            $cssItem->lock(60);

            // Go through all installed fonts each time and regenerate.
            $fontTemplate = '@font-face {
    font-family: \'[family]\';
    src: url(\'[url]\');
}';

            // Save a fonts.css file to the library for use as a module
            $fonts = $this->mediaFactory->getByMediaType('font');

            $css = '';
            $localCss = '';
            $ckEditorString = '';
            $fontList = [];

            // Check the library exists
            $libraryLocation = $this->getConfig()->getSetting('LIBRARY_LOCATION');
            $this->ensureLibraryExists($libraryLocation);

            if (count($fonts) > 0) {
                // Build our font strings.
                foreach ($fonts as $font) {
                    /* @var Media $font */

                    // Skip unreleased fonts
                    if ($font->released == 0) {
                        continue;
                    }

                    // Separate out the display name and the referenced name (referenced name cannot contain any odd characters or numbers)
                    $displayName = $font->name;
                    $familyName = strtolower(preg_replace('/\s+/', ' ', preg_replace('/\d+/u', '', $font->name)));

                    // Css for the player contains the actual stored as location of the font.
                    $css .= str_replace('[url]', $font->storedAs, str_replace('[family]', $familyName, $fontTemplate));
                    // Test to see if this user should have access to this font
                    if ($this->getUser()->checkViewable($font)) {
                        // Css for the local CMS contains the full download path to the font
                        $url = $this->urlFor($request, 'library.download',
                                ['type' => 'font', 'id' => $font->mediaId]) . '?download=1&downloadFromLibrary=1';
                        $localCss .= str_replace('[url]', $url, str_replace('[family]', $familyName, $fontTemplate));

                        // CKEditor string
                        $ckEditorString .= $displayName . '/' . $familyName . ';';

                        // Font list
                        $fontList[] = [
                            'displayName' => $displayName,
                            'familyName' => $familyName
                        ];
                    }
                }

                // If we're a full regenerate, we want to also update the fonts.css file.
                if ($options['invalidateCache']) {

                    // Pull out the currently stored fonts.css from the library (if it exists)
                    $existingLibraryFontsCss = '';
                    if (file_exists($libraryLocation . 'fonts.css')) {
                        $existingLibraryFontsCss = file_get_contents($libraryLocation . 'fonts.css');
                    }

                    // Put the player CSS into the temporary library location
                    $tempUrl = $this->getConfig()->getSetting('LIBRARY_LOCATION') . 'temp/fonts.css';
                    file_put_contents($tempUrl, $css);

                    // Install it (doesn't expire, isn't a system file, force update)
                    $media = $this->mediaFactory->createModuleSystemFile('fonts.css', $tempUrl);
                    $media->expires = 0;
                    $media->moduleSystemFile = true;
                    $media->isSaveRequired = true;
                    $media->save(['saveTags' => false]);

                    // We can remove the temp file
                    @unlink($tempUrl);

                    // Check to see if the existing file is different from the new one
                    if ($existingLibraryFontsCss == '' || md5($existingLibraryFontsCss) !== $media->md5) {
                        $this->getLog()->info('Detected change in fonts.css file, dropping the Display cache');
                        // Clear the display cache
                        $this->pool->deleteItem('/display');
                    } else {
                        $this->getLog()->debug('Newly generated font cache is the same as the old cache. Ignoring.');
                    }
                }

                $cssDetails = [
                    'css' => $localCss,
                    'ckeditor' => $ckEditorString,
                    'list' => $fontList
                ];

                $cssItem->set($cssDetails);
                $cssItem->expiresAfter(new \DateInterval('P30D'));
                $this->pool->saveDeferred($cssItem);
            }
        } else {
            $this->getLog()->debug('CMS font CSS returned from Cache.');
        }

        // Return a fonts css string for use locally (in the CMS)
        return $cssDetails;
    }

    /**
     * Installs all files related to the enabled modules
     * @throws NotFoundException
     * @throws GeneralException
     */
    public function installAllModuleFiles()
    {
        $this->getLog()->info('Installing all module files');

        // Do this for all enabled modules
        foreach ($this->moduleFactory->getEnabled() as $module) {
            /* @var \Xibo\Entity\Module $module */

            // Install Files for this module
            $moduleObject = $this->moduleFactory->create($module->type);
            $moduleObject->installFiles();
        }

        // Dump the cache on all displays
        foreach ($this->displayFactory->query() as $display) {
            /** @var \Xibo\Entity\Display $display */
            $display->notify();
        }
    }

    /**
     * Remove temporary files
     */
    public function removeTempFiles()
    {
        $libraryTemp = $this->getConfig()->getSetting('LIBRARY_LOCATION') . 'temp';

        if (!is_dir($libraryTemp))
            return;

        // Dump the files in the temp folder
        foreach (scandir($libraryTemp) as $item) {
            if ($item == '.' || $item == '..')
                continue;

            // Has this file been written to recently?
            if (filemtime($libraryTemp . DIRECTORY_SEPARATOR . $item) > Carbon::now()->subSeconds(86400)->format('U')) {
                $this->getLog()->debug('Skipping active file: ' . $item);
                continue;
            }

            $this->getLog()->debug('Deleting temp file: ' . $item);

            unlink($libraryTemp . DIRECTORY_SEPARATOR . $item);
        }
    }

    /**
     * Removes all expired media files
     * @throws NotFoundException
     * @throws GeneralException
     */
    public function removeExpiredFiles()
    {
        // Get a list of all expired files and delete them
        foreach ($this->mediaFactory->query(null, array('expires' => Carbon::now()->format('U'), 'allModules' => 1, 'length' => 100)) as $entry) {
            /* @var \Xibo\Entity\Media $entry */
            // If the media type is a module, then pretend its a generic file
            $this->getLog()->info('Removing Expired File %s', $entry->name);
            $entry->setChildObjectDependencies($this->layoutFactory, $this->widgetFactory, $this->displayGroupFactory, $this->displayFactory, $this->scheduleFactory, $this->playerVersionFactory);
            $entry->delete();
        }
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param $id
     * @throws AccessDeniedException
     * @throws ConfigurationException
     * @throws GeneralException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function mcaas(Request $request, Response $response, $id)
    {
        // TODO MCAAS
        // This is only available through the API
        if (!$this->isApi($request)) {
            throw new AccessDeniedException(__('Route is available through the API'));
        }

        // We need to get the access token we used to authorize this request.
        // as we are API we can expect that in the $app.
        /** @var $accessToken \League\OAuth2\Server\Entity\AccessTokenEntity */
        $accessToken = $this->getApp()->server->getAccessToken();

        // Call Add with the oldMediaId
        $this->add([
            'oldMediaId' => $id,
            'updateInLayouts' => 1,
            'deleteOldRevisions' => 1,
            'allowMediaTypeChange' => 1
        ]);

        // Expire the token
        $accessToken->expire();
    }

    /**
     * @SWG\Post(
     *  path="/library/{mediaId}/tag",
     *  operationId="mediaTag",
     *  tags={"library"},
     *  summary="Tag Media",
     *  description="Tag a Media with one or more tags",
     * @SWG\Parameter(
     *      name="mediaId",
     *      in="path",
     *      description="The Media Id to Tag",
     *      type="integer",
     *      required=true
     *   ),
     * @SWG\Parameter(
     *      name="tag",
     *      in="formData",
     *      description="An array of tags",
     *      type="array",
     *      required=true,
     *      @SWG\Items(type="string")
     *   ),
     *  @SWG\Response(
     *      response=200,
     *      description="successful operation",
     *      @SWG\Schema(ref="#/definitions/Media")
     *  )
     * )
     *
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws ConfigurationException
     * @throws GeneralException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\DuplicateEntityException
     */
    public function tag(Request $request, Response $response, $id)
    {
        // Edit permission
        // Get the media
        $media = $this->mediaFactory->getById($id);

        // Check Permissions
        if (!$this->getUser()->checkEditable($media)) {
            throw new AccessDeniedException();
        }

        $tags = $this->getSanitizer($request->getParams())->getArray('tag');

        if (count($tags) <= 0) {
            throw new InvalidArgumentException(__('No tags to assign'));
        }

        foreach ($tags as $tag) {
            $media->assignTag($this->tagFactory->tagFromString($tag));
        }

        $media->save(['validate' => false]);

        // Return
        $this->getState()->hydrate([
            'message' => sprintf(__('Tagged %s'), $media->name),
            'id' => $media->mediaId,
            'data' => $media
        ]);

        return $this->render($request, $response);
    }

    /**
     * @SWG\Post(
     *  path="/library/{mediaId}/untag",
     *  operationId="mediaUntag",
     *  tags={"library"},
     *  summary="Untag Media",
     *  description="Untag a Media with one or more tags",
     * @SWG\Parameter(
     *      name="mediaId",
     *      in="path",
     *      description="The Media Id to Untag",
     *      type="integer",
     *      required=true
     *   ),
     * @SWG\Parameter(
     *      name="tag",
     *      in="formData",
     *      description="An array of tags",
     *      type="array",
     *      required=true,
     *      @SWG\Items(type="string")
     *   ),
     *  @SWG\Response(
     *      response=200,
     *      description="successful operation",
     *      @SWG\Schema(ref="#/definitions/Media")
     *  )
     * )
     *
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws ConfigurationException
     * @throws GeneralException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\DuplicateEntityException
     */
    public function untag(Request $request, Response $response, $id)
    {
        // Edit permission
        // Get the media
        $media = $this->mediaFactory->getById($id);

        // Check Permissions
        if (!$this->getUser()->checkEditable($media)) {
            throw new AccessDeniedException();
        }

        $tags = $this->getSanitizer($request->getParams())->getArray('tag');

        if (count($tags) <= 0) {
            throw new InvalidArgumentException(__('No tags to unassign'), 'tag');
        }

        foreach ($tags as $tag) {
            $media->unassignTag($this->tagFactory->tagFromString($tag));
        }

        $media->save(['validate' => false]);

        // Return
        $this->getState()->hydrate([
            'message' => sprintf(__('Untagged %s'), $media->name),
            'id' => $media->mediaId,
            'data' => $media
        ]);

        return $this->render($request, $response);
    }

    /**
     * Library Usage Report Form
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function usageForm(Request $request, Response $response, $id)
    {
        $media = $this->mediaFactory->getById($id);

        if (!$this->getUser()->checkViewable($media)) {
            throw new AccessDeniedException();
        }

        // Get a list of displays that this mediaId is used on
        $displays = $this->displayFactory->query($this->gridRenderSort($request), $this->gridRenderFilter(['disableUserCheck' => 1, 'mediaId' => $id], $request));

        $this->getState()->template = 'library-form-usage';
        $this->getState()->setData([
            'media' => $media,
            'countDisplays' => count($displays)
        ]);

        return $this->render($request, $response);
    }

    /**
     * @SWG\Get(
     *  path="/library/usage/{mediaId}",
     *  operationId="libraryUsageReport",
     *  tags={"library"},
     *  summary="Get Library Item Usage Report",
     *  description="Get the records for the library item usage report",
     * @SWG\Parameter(
     *      name="mediaId",
     *      in="path",
     *      description="The Media Id",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Response(
     *     response=200,
     *     description="successful operation"
     *  )
     * )
     *
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function usage(Request $request, Response $response, $id)
    {
        $media = $this->mediaFactory->getById($id);
        $sanitizedParams = $this->getSanitizer($request->getParams());

        if (!$this->getUser()->checkViewable($media)) {
            throw new AccessDeniedException();
        }

        // Get a list of displays that this mediaId is used on by direct assignment
        $displays = $this->displayFactory->query($this->gridRenderSort($request), $this->gridRenderFilter(['mediaId' => $id], $request));

        // have we been provided with a date/time to restrict the scheduled events to?
        $mediaFromDate = $sanitizedParams->getDate('mediaEventFromDate');
        $mediaToDate = $sanitizedParams->getDate('mediaEventToDate');

        // Media query array
        $mediaQuery = [
            'mediaId' => $id
        ];

        if ($mediaFromDate !== null) {
            $mediaQuery['futureSchedulesFrom'] = $mediaFromDate->format('U');
        }

        if ($mediaToDate !== null) {
            $mediaQuery['futureSchedulesTo'] = $mediaToDate->format('U');
        }

        // Query for events
        $events = $this->scheduleFactory->query(null, $mediaQuery);

        // Total records returned from the schedules query
        $totalRecords = $this->scheduleFactory->countLast();

        foreach ($events as $row) {
            /* @var \Xibo\Entity\Schedule $row */

            // Generate this event
            // Assess the date?
            if ($mediaFromDate !== null && $mediaToDate !== null) {
                try {
                    $scheduleEvents = $row->getEvents($mediaFromDate, $mediaToDate);
                } catch (GeneralException $e) {
                    $this->getLog()->error('Unable to getEvents for ' . $row->eventId);
                    continue;
                }

                // Skip events that do not fall within the specified days
                if (count($scheduleEvents) <= 0)
                    continue;

                $this->getLog()->debug('EventId ' . $row->eventId . ' as events: ' . json_encode($scheduleEvents));
            }

            // Load the display groups
            $row->load();

            foreach ($row->displayGroups as $displayGroup) {
                foreach ($this->displayFactory->getByDisplayGroupId($displayGroup->displayGroupId) as $display) {
                    $found = false;

                    // Check to see if our ID is already in our list
                    foreach ($displays as $existing) {
                        if ($existing->displayId === $display->displayId) {
                            $found = true;
                            break;
                        }
                    }

                    if (!$found)
                        $displays[] = $display;
                }
            }
        }

        if ($this->isApi($request) && $displays == []) {
            $displays = [
                'data' =>__('Specified Media item is not in use.')];
        }

        $this->getState()->template = 'grid';
        $this->getState()->recordsTotal = $totalRecords;
        $this->getState()->setData($displays);

        return $this->render($request, $response);
    }

    /**
     * @SWG\Get(
     *  path="/library/usage/layouts/{mediaId}",
     *  operationId="libraryUsageLayoutsReport",
     *  tags={"library"},
     *  summary="Get Library Item Usage Report for Layouts",
     *  description="Get the records for the library item usage report for Layouts",
     * @SWG\Parameter(
     *      name="mediaId",
     *      in="path",
     *      description="The Media Id",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Response(
     *     response=200,
     *     description="successful operation"
     *  )
     * )
     *
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function usageLayouts(Request $request, Response $response, $id)
    {
        $media = $this->mediaFactory->getById($id);

        if (!$this->getUser()->checkViewable($media)) {
            throw new AccessDeniedException();
        }

        $layouts = $this->layoutFactory->query(null, ['mediaId' => $id, 'showDrafts' => 1]);

        if (!$this->isApi($request)) {
            foreach ($layouts as $layout) {
                $layout->includeProperty('buttons');

                // Add some buttons for this row
                if ($this->getUser()->checkEditable($layout)) {
                    // Design Button
                    $layout->buttons[] = array(
                        'id' => 'layout_button_design',
                        'linkType' => '_self', 'external' => true,
                        'url' => $this->urlFor($request,'layout.designer', ['id' => $layout->layoutId]),
                        'text' => __('Design')
                    );
                }

                // Preview
                $layout->buttons[] = array(
                    'id' => 'layout_button_preview',
                    'linkType' => '_blank',
                    'external' => true,
                    'url' => $this->urlFor($request,'layout.preview', ['id' => $layout->layoutId]),
                    'text' => __('Preview Layout')
                );
            }
        }

        if ($this->isApi($request) && $layouts == []) {
            $layouts = [
                'data' =>__('Specified Media item is not in use.')
            ];
        }

        $this->getState()->template = 'grid';
        $this->getState()->recordsTotal = $this->layoutFactory->countLast();
        $this->getState()->setData($layouts);

        return $this->render($request, $response);
    }

    /**
     * Copy Media form
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function copyForm(Request $request, Response $response, $id)
    {
        // Get the Media
        $media = $this->mediaFactory->getById($id);

        // Check Permissions
        if (!$this->getUser()->checkViewable($media)) {
            throw new AccessDeniedException();
        }

        $this->getState()->template = 'library-form-copy';
        $this->getState()->setData([
            'media' => $media,
            'help' => $this->getHelp()->link('Media', 'Copy'),
            'tags' => $this->tagFactory->getTagsWithValues($media)
        ]);

        return $this->render($request, $response);
    }

    /**
     * Copies a Media
     *
     * @SWG\Post(
     *  path="/library/copy/{mediaId}",
     *  operationId="mediaCopy",
     *  tags={"library"},
     *  summary="Copy Media",
     *  description="Copy a Media, providing a new name and tags if applicable",
     *  @SWG\Parameter(
     *      name="mediaId",
     *      in="path",
     *      description="The media ID to Copy",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="name",
     *      in="formData",
     *      description="The name for the new Media",
     *      type="string",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="tags",
     *      in="formData",
     *      description="The Optional tags for new Media",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Response(
     *      response=201,
     *      description="successful operation",
     *      @SWG\Schema(ref="#/definitions/Media"),
     *      @SWG\Header(
     *          header="Location",
     *          description="Location of the new record",
     *          type="string"
     *      )
     *  )
     * )
     *
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws ConfigurationException
     * @throws GeneralException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\DuplicateEntityException
     */
    public function copy(Request $request, Response $response, $id)
    {
        // Get the Media
        $media = $this->mediaFactory->getById($id);
        $sanitizedParams = $this->getSanitizer($request->getParams());

        // Check Permissions
        if (!$this->getUser()->checkViewable($media)) {
            throw new AccessDeniedException();
        }

        // Load the media for Copy
        $media->load();
        $media = clone $media;

        // Set new Name and tags
        $media->name = $sanitizedParams->getString('name');

        if ($this->getUser()->featureEnabled('tag.tagging')) {
            $media->replaceTags($this->tagFactory->tagsFromString($sanitizedParams->getString('tags')));
        }

        // Set the Owner to user making the Copy
        $media->setOwner($this->getUser()->userId);

        // Set from global setting
        if ($media->enableStat == null) {
            $media->enableStat = $this->getConfig()->getSetting('MEDIA_STATS_ENABLED_DEFAULT');
        }

        // Save the new Media
        $media->save();

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 201,
            'message' => sprintf(__('Copied as %s'), $media->name),
            'id' => $media->mediaId,
            'data' => $media
        ]);

        return $this->render($request,  $response);
    }


    /**
     * @SWG\Get(
     *  path="/library/{mediaId}/isused/",
     *  operationId="mediaIsUsed",
     *  tags={"library"},
     *  summary="Media usage check",
     *  description="Checks if a Media is being used",
     * @SWG\Parameter(
     *      name="mediaId",
     *      in="path",
     *      description="The Media Id",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Response(
     *     response=200,
     *     description="successful operation"
     *  )
     * )
     *
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function isUsed(Request $request, Response $response, $id)
    {
        // Get the Media
        $media = $this->mediaFactory->getById($id);
        $media->setChildObjectDependencies($this->layoutFactory, $this->widgetFactory, $this->displayGroupFactory, $this->displayFactory, $this->scheduleFactory, $this->playerVersionFactory);

        // Check Permissions
        if (!$this->getUser()->checkViewable($media)) {
            throw new AccessDeniedException();
        }

        // Get count, being the number of times the media needs to appear to be true ( or use the default 0)
        $count = $this->getSanitizer($request->getParams())->getInt('count', ['default' => 0]);

        // Check and return result
        $this->getState()->setData([
            'isUsed' => $media->isUsed($count)
        ]);

        return $this->render($request, $response);
        
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws GeneralException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function uploadFromUrlForm(Request $request, Response $response)
    {
        $this->getState()->template = 'library-form-uploadFromUrl';

        $this->getState()->setData([
            'uploadSizeMessage' => sprintf(__('This form accepts files up to a maximum size of %s'), Environment::getMaxUploadSize())
        ]);

        return $this->render($request, $response);
    }

    /**
     * Upload Media via URL
     *
     * @SWG\Post(
     *  path="/library/uploadUrl",
     *  operationId="uploadFromUrl",
     *  tags={"library"},
     *  summary="Upload Media from URL",
     *  description="Upload Media to CMS library from an external URL",
     *  @SWG\Parameter(
     *      name="url",
     *      in="formData",
     *      description="The URL to the media",
     *      type="string",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="type",
     *      in="formData",
     *      description="The type of the media, image, video etc",
     *      type="string",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="extension",
     *      in="formData",
     *      description="Optional extension of the media, jpg, png etc. If not set in the request it will be retrieved from the headers",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="enableStat",
     *      in="formData",
     *      description="The option to enable the collection of Media Proof of Play statistics, On, Off or Inherit.",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="optionalName",
     *      in="formData",
     *      description="An optional name for this media file, if left empty it will default to the file name",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="expires",
     *      in="formData",
     *      description="Date in Y-m-d H:i:s format, will set expiration date on the Media item",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="folderId",
     *      in="formData",
     *      description="Folder ID to which this media should be assigned to",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Response(
     *      response=201,
     *      description="successful operation",
     *      @SWG\Schema(ref="#/definitions/Media"),
     *      @SWG\Header(
     *          header="Location",
     *          description="Location of the new record",
     *          type="string"
     *      )
     *  )
     * )
     *
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws ConfigurationException
     * @throws GeneralException
     * @throws InvalidArgumentException
     * @throws LibraryFullException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\DuplicateEntityException
     */
    public function uploadFromUrl(Request $request, Response $response)
    {
        $libraryFolder = $this->getConfig()->getSetting('LIBRARY_LOCATION');
        $sanitizedParams = $this->getSanitizer($request->getParams());

        // Make sure the library exists
        self::ensureLibraryExists($libraryFolder);

        $url = $sanitizedParams->getString('url');
        $type = $sanitizedParams->getString('type');
        $optionalName = $sanitizedParams->getString('optionalName');
        $extension = $sanitizedParams->getString('extension');
        $enableStat = $sanitizedParams->getString('enableStat', ['default' => $this->getConfig()->getSetting('MEDIA_STATS_ENABLED_DEFAULT')]);
        $folderId = $sanitizedParams->getInt('folderId', ['default' => 1]);
        $folder = $this->folderFactory->getById($folderId);
        $permissionsFolderId = ($folder->permissionsFolderId == null) ? $folder->id : $folder->permissionsFolderId;
        
        if ($sanitizedParams->getDate('expires') != null ) {

            if ($sanitizedParams->getDate('expires')->format('U') > Carbon::now()->format('U')) {
                $expires = $sanitizedParams->getDate('expires')->format('U');
            } else {
                throw new InvalidArgumentException(__('Cannot set Expiry date in the past'), 'expires');
            }
        } else {
            $expires = 0;
        }

        // Validate the URL
        if (!v::url()->notEmpty()->validate(urldecode($url)) || !filter_var($url, FILTER_VALIDATE_URL)) {
            throw new InvalidArgumentException(__('Provided URL is invalid'), 'url');
        }

        $librarySizeLimit = $this->getConfig()->getSetting('LIBRARY_SIZE_LIMIT_KB') * 1024;
        $librarySizeLimitMB = round(($librarySizeLimit / 1024) / 1024, 2);

        if ($librarySizeLimit > 0 && $this->libraryUsage() > $librarySizeLimit) {
            throw new InvalidArgumentException(sprintf(__('Your library is full. Library Limit: %s MB'), $librarySizeLimitMB), 'libraryLimit');
        }

        // remote file size
        $size = $this->getRemoteFileSize($url);

        if (ByteFormatter::toBytes(Environment::getMaxUploadSize()) < $size) {
            throw new InvalidArgumentException(sprintf(__('This file size exceeds your environment Max Upload Size %s'), Environment::getMaxUploadSize()), 'size');
        }

        $this->getUser()->isQuotaFullByUser();

        // check if we have extension provided in the request (available via API), if not get it from the headers
        if (!empty($extension)) {
            $ext = $extension;
        } else {
            $ext = $this->getRemoteFileExtension($url);
        }

        // check if we have type provided in the request (available via API), if not get the module type from the extension
        if (!empty($type)) {
            $module = $this->getModuleFactory()->create($type);
        } else {
            $module = $this->getModuleFactory()->getByExtension($ext);
            $module = $this->getModuleFactory()->create($module->type);
        }

        // if we were provided with optional Media name set it here, otherwise get it from pathinfo
        if (!empty($optionalName)) {
            $name = $optionalName;
        } else {
            // get the media name from pathinfo
            $name = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_FILENAME);
        }

        // double check that provided Module Type and Extension are valid
        $moduleCheck = $this->getModuleFactory()->query(null, ['extension' => $ext, 'type' => $module->getModuleType()]);

        if (count($moduleCheck) <= 0) {
            throw new NotFoundException(sprintf(__('Invalid Module type or extension. Module type %s does not allow for %s extension'), $module->getModuleType(), $ext));
        }

        // add our media to queueDownload and process the downloads
        $this->mediaFactory->queueDownload($name, str_replace(' ', '%20', htmlspecialchars_decode($url)), $expires, ['fileType' => strtolower($module->getModuleType()), 'duration' => $module->determineDuration(), 'extension' => $ext, 'enableStat' => $enableStat, 'folderId' => $folderId, 'permissionsFolderId' => $permissionsFolderId]);
        $this->mediaFactory->processDownloads(function($media) {
            // Success
            $this->getLog()->debug('Successfully uploaded Media from URL, Media Id is ' . $media->mediaId);
        });

        // get our uploaded media
        $media = $this->mediaFactory->getByName($name);

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 201,
            'message' => sprintf(__('Media upload from URL was successful')),
            'id' => $media->mediaId,
            'data' => $media
        ]);

        return $this->render($request, $response);
    }

    /**
     * @param $url
     * @return int
     * @throws InvalidArgumentException
     */
    private function getRemoteFileSize($url)
    {
        $size = -1;
        $guzzle = new Client($this->getConfig()->getGuzzleProxy());

        try {
            $head = $guzzle->head($url);
            $contentLength = $head->getHeader('Content-Length');

            foreach ($contentLength as $value) {
                $size = $value;
            }

        } catch (RequestException $e) {
            $this->getLog()->debug('Upload from url failed for URL ' . $url . ' with following message ' . $e->getMessage());
            throw new InvalidArgumentException(('File not found'), 'url');
        }

        if ($size <= 0) {
            throw new InvalidArgumentException(('Cannot determine the file size'), 'size');
        }

        return (int)$size;
    }

    /**
     * @param $url
     * @return string
     * @throws InvalidArgumentException
     */
    private function getRemoteFileExtension($url)
    {
        // first try to get the extension from pathinfo
        $extension = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION);

        // failing that get the extension from Content-Type header via Guzzle
         if ($extension == '') {
             $guzzle = new Client($this->getConfig()->getGuzzleProxy());
             $head = $guzzle->head($url);
             $contentType = $head->getHeaderLine('Content-Type');

             $extension = $contentType;

             if ($contentType === 'binary/octet-stream' && $head->hasHeader('x-amz-meta-filetype')) {
                 $amazonContentType = $head->getHeaderLine('x-amz-meta-filetype');
                 $extension = $amazonContentType;
             }

             // get the extension corresponding to the mime type
             $mimeTypes = new MimeTypes();
             $extension = $mimeTypes->getExtension($extension);
         }

         // if we could not determine the file extension at this point, throw an error
        if ($extension == '') {
            throw new InvalidArgumentException(('Cannot determine the file extension'), 'extension');
        }

        return $extension;
    }

    /**
     * This is called when video finishes uploading.
     * Saves provided base64 image as an actual image to the library
     *
     * @param Request $request
     * @param Response $response
     *
     * @return Response
     * @throws InvalidArgumentException
     * @throws NotFoundException
     */
    public function addThumbnail($request, $response)
    {
        $sanitizedParams = $this->getSanitizer($request->getParams());
        $libraryLocation = $this->getConfig()->getSetting('LIBRARY_LOCATION');

        $image = $request->getParam('image');
        $mediaId = $sanitizedParams->getInt('mediaId');
        $media = $this->mediaFactory->getById($mediaId);

        if (preg_match('/^data:image\/(\w+);base64,/', $image, $type)) {
            $image = substr($image, strpos($image, ',') + 1);
            $type = strtolower($type[1]);

            if (!in_array($type, [ 'jpg', 'jpeg', 'gif', 'png' ])) {
                throw new InvalidArgumentException(__('Provided base64 encoded image has incorrect file extension.'));
            }
            $image = str_replace( ' ', '+', $image );
            $image = base64_decode($image);

            if ($image === false) {
                throw new InvalidArgumentException(__("Image decoding failed."));
            }
        } else {
            throw new InvalidArgumentException(__('Incorrect image data'));
        }

        file_put_contents($libraryLocation . "{$mediaId}_{$media->mediaType}cover.{$type}", $image);

        return $response->withStatus(204);
    }

    /**
     * Select Folder Form
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function selectFolderForm(Request $request, Response $response, $id)
    {
        // Get the Media
        $media = $this->mediaFactory->getById($id);

        // Check Permissions
        if (!$this->getUser()->checkEditable($media)) {
            throw new AccessDeniedException();
        }

        $data = [
            'media' => $media
        ];

        $this->getState()->template = 'library-form-selectfolder';
        $this->getState()->setData($data);

        return $this->render($request, $response);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws ConfigurationException
     * @throws GeneralException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\DuplicateEntityException
     */
    public function selectFolder(Request $request, Response $response, $id)
    {
        // Get the Media
        $media = $this->mediaFactory->getById($id);

        // Check Permissions
        if (!$this->getUser()->checkEditable($media)) {
            throw new AccessDeniedException();
        }

        $folderId = $this->getSanitizer($request->getParams())->getInt('folderId');

        $media->folderId = $folderId;
        $media->save(['saveTags' => false]);

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 204,
            'message' => sprintf(__('Media %s moved to Folder %d'), $media->name, $folderId)
        ]);

        return $this->render($request, $response);
    }
}
