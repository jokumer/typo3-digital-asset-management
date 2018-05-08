<?php
declare(strict_types = 1);
namespace TYPO3\CMS\DigitalAssetManagement\Controller;

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

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Resource\ResourceStorage;
use TYPO3\CMS\DigitalAssetManagement\Service\FileSystemInterface;

/**
 * Backend controller: The "Digital Asset Management" JSON response controller
 *
 * Optional replacement of filelist
 */
class DigitalAssetManagementAjaxController
{
    /**
     * Main entry method: Dispatch to other actions - those method names that end with "Action".
     *
     * @param ServerRequestInterface $request the current request
     * @return ResponseInterface the response with the content
     */
    public function handleAjaxRequestAction(ServerRequestInterface $request): ResponseInterface
    {
        $response = new JsonResponse();
        $result = [];
        $result['method'] = '';
        $result['params'] = [];
        $params = $request->getQueryParams();
        // Execute all query params starting with get using its values as parameter
        foreach ($params as $key => $param) {
            if ($key === 'method') {
                $result['method'] = $param;
                $func = $param . 'Action';
            } elseif ($key === 'params') {
                $result['params'] = $param;
            }
        }
        if ($func && is_callable([$this, $func])) {
            $result['result'] = call_user_func(array(DigitalAssetManagementAjaxController::class, $func), $result['params']);
        }
        $response->setPayload($result);
        return $response;
    }

    /**
     * get file and folder content for a path
     * empty string means get all storages or mounts of the be-user or the root level of a single available storage
     *
     * @param string|array $params
     * @return array
     */
    protected function getContentAction($params = "")
    {
        if (is_array($params)) {
            $path = reset($params);
        } else {
            $path = $params;
        }
        $backendUser = $this->getBackendUser();
        // Get all storage objects
        /** @var ResourceStorage[] $fileStorages */
        $fileStorages = $backendUser->getFileStorages();
        /** @var FileSystemInterface $service */
        $service = null;
        // $result['debug'] = \TYPO3\CMS\Extbase\Utility\DebuggerUtility::var_dump($fileStorages,null, 8, false, true,true);
        if (is_array($fileStorages)){
            $storageId = null;
            if ($path === "") {
                $settings = new \TYPO3\CMS\DigitalAssetManagement\Utility\UserSettings();
                $path = $settings->getSavingPosition();
            } elseif ($path === "*" ) {
                $path = "";
            }
            if (($path !== "") && (strlen($path) > 1)) {
                list($storageId, $path) = explode(":", $path, 2);
            }
            if ($path === "") {
                $path = "/";
            }
            $files = [];
            $folders = [];
            $breadcrumbs = [];
            $breadcrumbs[] = [
                'identifier' => '*',
                'name' => 'home',
                'type' => 'home'
            ];
            $relPath = $path;
            /** @var ResourceStorage $fileStorage  */
            if ($storageId === null) {
                // no storage, mountpoint and folder selected
                if (count($fileStorages) > 1) {
                    // more than one storage
                    foreach ($fileStorages as $fileStorage) {
                        $storageInfo = $fileStorage->getStorageRecord();
                        $fileMounts = $fileStorage->getFileMounts();
                        if (!empty($fileMounts)) {
                            // mount points exists in the storage
                            foreach ($fileMounts as $fileMount) {
                                $folders[] = [
                                    'identifier' => $storageInfo['uid'] . ':' . $fileMount['path'],
                                    'name' => $fileMount['title'],
                                    'storage_name' => $storageInfo['name'],
                                    'storage' => $storageInfo['uid'],
                                    'type' => 'mount'
                                ];
                            }
                            unset($fileMounts);
                        } else {
                            // no mountpoint exists in the storage
                            $folders[] = [
                                'identifier' => $storageInfo['uid'] . ':',
                                'name' => $storageInfo['name'],
                                'storage_name' => $storageInfo['name'],
                                'storage' => $storageInfo['uid'],
                                'type' => 'storage'
                            ];
                        }
                        unset($storageInfo);
                    }
                } else {
                    // only one storage
                    $fileStorage = reset($fileStorages);
                    $storageInfo = $fileStorage->getStorageRecord();
                    $fileMounts = $fileStorage->getFileMounts();
                    if (count($fileMounts) > 1) {
                        // more than one mountpoint
                        foreach ($fileMounts as $fileMount) {
                            $folders[] = [
                                'identifier' => $storageInfo['uid'] . ':' . $fileMount['path'],
                                'name' => $fileMount['title'],
                                'storage_name' => $storageInfo['name'],
                                'storage' => $storageInfo['uid'],
                                'type' => 'mount'
                            ];
                        }
                        unset($fileMounts);
                    } else {
                        // only one mountpoint
                        $service = new \TYPO3\CMS\DigitalAssetManagement\Service\LocalFileSystemService($fileStorage);
                        if ($service) {
                            $files = $service->listFiles($path);
                            $folders = $service->listFolder($path);
                            unset($service);
                        }
                    }
                    unset($storageInfo);
                }
            } else {
                // storage or mountpoint selected
                foreach ($fileStorages as $fileStorage) {
                    $storageInfo = $fileStorage->getStorageRecord();
                    if ((count($fileStorages) === 1) || ($storageId && ($storageInfo['uid'] == $storageId))) {
                        // selected storage
                        $identifier = $storageInfo['uid'] . ':';
                        $fileMounts = $fileStorage->getFileMounts();
                        if (!empty($fileMounts)) {
                            // mountpoint exists
                            foreach ($fileMounts as $fileMount) {
                                if (strpos($path, $fileMount['path']) === 0) {
                                    $identifier .= $fileMount['path'];
                                    $breadcrumbs[] = [
                                        'identifier' => $identifier,
                                        'name' => $fileMount['title'],
                                        'type' => 'mount'
                                    ];
                                    $relPath = str_replace($fileMount['path'], '', $relPath);
                                }
                            }
                            unset($fileMounts);
                        } else {
                            // no mountpoint exists but more than one storage
                            $identifier .= '/';
                            if (count($fileStorages) > 1) {
                                $breadcrumbs[] = [
                                    'identifier' => $identifier,
                                    'name' => $storageInfo['name'],
                                    'type' => 'storage'
                                ];
                            }
                        }
                        $aPath = explode('/', $relPath);
                        for ($i = 0; $i < count($aPath); $i++) {
                            if ($aPath[$i] !== '') {
                                $identifier .= $aPath[$i] . '/';
                                $breadcrumbs[] = [
                                    'identifier' => $identifier,
                                    'name' => $aPath[$i],
                                    'type' => 'folder'
                                ];
                            }
                        }
                        $service = new \TYPO3\CMS\DigitalAssetManagement\Service\LocalFileSystemService($fileStorage);
                        if ($service) {
                            $files = $service->listFiles($path);
                            $folders = $service->listFolder($path);
                            unset($service);
                        }
                        $settings = new \TYPO3\CMS\DigitalAssetManagement\Utility\UserSettings();
                        $settings->setSavingPosition($identifier);
                        break;
                    }
                }
            }
            return ['files' => $files, 'folders' => $folders, 'breadcrumbs' => $breadcrumbs];
        }
    }

    /**
     * get thumbnail from image file
     * only local storages are supported until now
     *
     * @param string|array $params
     * @return array
     */
    protected function getThumbnailAction($params = "")
    {
        if (is_array($params)) {
            $path = reset($params);
        } else {
            $path = $params;
        }
        $backendUser = $this->getBackendUser();
        // Get all storage objects
        /** @var ResourceStorage[] $fileStorages */
        $fileStorages = $backendUser->getFileStorages();
        /** @var FileSystemInterface $service */
        $service = null;
        //$result['debug'] = \TYPO3\CMS\Extbase\Utility\DebuggerUtility::var_dump($fileStorages,null, 8, false, true,true);
        if (is_array($fileStorages) && (strlen($path)>6)) {
            list($storageId, $path) = explode(":", $path, 2);
            if ($storageId && !empty($path)) {
                /** @var ResourceStorage $fileStorage  */
                foreach ($fileStorages as $fileStorage) {
                    if (($fileStorage->getUid() == $storageId) && ($fileStorage->getDriverType() === 'Local')) {
                        $service = new \TYPO3\CMS\DigitalAssetManagement\Service\LocalFileSystemService($fileStorage);
                        if ($service) {
                            $file = $fileStorage->getFile($path);
                            $thumb = $service->thumbnail(rtrim($_SERVER["DOCUMENT_ROOT"],"/").'/'.urldecode($file->getPublicUrl()), true);
                            unset($service);
                        }
                        break;
                    }
                }
            }
            return ['thumbnail' => $thumb];
        }
    }

    /**
     * Returns an instance of LanguageService
     *
     * @return \TYPO3\CMS\Core\Localization\LanguageService
     */
    protected function getLanguageService()
    {
        return $GLOBALS['LANG'];
    }

    /**
     * Returns the current BE user.
     *
     * @return \TYPO3\CMS\Core\Authentication\BackendUserAuthentication
     */
    protected function getBackendUser()
    {
        return $GLOBALS['BE_USER'];
    }
}
