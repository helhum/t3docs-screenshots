<?php

declare(strict_types=1);
namespace TYPO3\CMS\Screenshots\Controller;

/*
 * This file is part of the TYPO3 project.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

use SensioLabs\AnsiConverter\AnsiToHtmlConverter;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Mvc\View\ViewInterface;
use TYPO3\CMS\Fluid\ViewHelpers\Be\InfoboxViewHelper;
use TYPO3\CMS\Screenshots\Comparison\File;
use TYPO3\CMS\Screenshots\Comparison\ImageComparison;

class ScreenshotsManagerController extends ActionController
{
    protected float $threshold = 0.0002;
    protected array $imageExtensions = ['gif', 'jpg', 'jpeg', 'png', 'bmp'];

    protected PageRenderer $pageRenderer;

    public function injectPageRenderer(PageRenderer $pageRenderer)
    {
        $this->pageRenderer = $pageRenderer;
    }

    protected function initializeView(ViewInterface $view)
    {
        parent::initializeView($view);

        $this->pageRenderer->addInlineLanguageLabelFile('EXT:screenshots/Resources/Private/Language/locallang_mod.xlf');
        $this->pageRenderer->addCssFile('EXT:screenshots/Resources/Public/Css/screenshots-manager.css');
        $this->pageRenderer->loadRequireJsModule('TYPO3/CMS/Screenshots/ScreenshotsManager');
    }

    public function indexAction()
    {
    }

    public function makeAction(): void
    {
        $command = 'typo3DatabaseName=func_test typo3DatabaseUsername=root typo3DatabasePassword=root typo3DatabaseHost=db ' .
            '/var/www/html/vendor/bin/codecept run -d -c /var/www/html/public/typo3conf/ext/screenshots/Classes/Runner/codeception.yml';

        $output = sprintf('$ %s', $command) . "\n";
        exec($command . " 2>&1", $outputArray, $resultCode);
        $output .= implode("\n", $outputArray);

        $converter = new AnsiToHtmlConverter();
        $outputHtml = $converter->convert($output);

        $this->view->assign('outputHtml', $outputHtml);
        $this->view->assign('resultCode', $resultCode);
        $this->view->assign('messages', $this->fetchMessages());
    }

    public function compareAction(
        string $cmd = 'compare',
        string $search = '',
        array $imagesToCopy = [],
        array $textFilesToCopy = [],
        int $numImages = 0,
        int $numTextFiles = 0
    ): void
    {
        if ($cmd === 'copy') {
            $this->copy($imagesToCopy, $textFilesToCopy, $numImages, $numTextFiles);
        }
        $this->compare($search);

        $this->view->assign('messages', $this->fetchMessages());
    }

    protected function compare(string $search): void
    {
        $folderOriginal = 't3docs';
        $folderActual = 't3docs-generated/actual';
        $folderDiff = 't3docs-generated/diff';

        $pathOriginal = GeneralUtility::getFileAbsFileName($folderOriginal);
        $pathActual = GeneralUtility::getFileAbsFileName($folderActual);
        $pathDiff = GeneralUtility::getFileAbsFileName($folderDiff);

        $urlActual = '/' . $folderActual;
        $urlOriginal = '/' . $folderOriginal;
        $urlDiff = '/' . $folderDiff;

        GeneralUtility::rmdir($pathDiff, true);
        $files = GeneralUtility::removePrefixPathFromList(GeneralUtility::getAllFilesAndFoldersInPath(
            [], $pathActual . '/'
        ), $pathActual);

        $imageExtensionsIndex = array_flip($this->imageExtensions);

        $isSearch = !empty($search);
        $isSearchByRegexp = strpos($search, '#') === 0;

        $imageComparisons = [];
        $textFiles = [];
        foreach ($files as $file) {
            $fileExtension = pathinfo($file, PATHINFO_EXTENSION);

            if (isset($imageExtensionsIndex[$fileExtension])) {
                if ($isSearch) {
                    if ($isSearchByRegexp) {
                        if (preg_match($search, $urlActual . $file) !== 1) {
                            continue;
                        }
                    } else {
                        if (strpos($urlActual . $file, $search) === false) {
                            continue;
                        }
                    }
                }

                $imageComparison = new ImageComparison(
                    $pathActual . $file,
                    $pathOriginal . $file,
                    $pathDiff . $file,
                    $urlActual . $file,
                    $urlOriginal . $file,
                    $urlDiff . $file,
                    $this->threshold
                );
                $imageComparison->process();
                if ($imageComparison->getDifference() > $this->threshold) {
                    $imageComparisons[] = $imageComparison;
                }
            } else {
                $textFiles[] = new File($pathActual . $file, $urlActual . $file);
            }
        }

        $this->view->assign('imageComparisons', $imageComparisons);
        $this->view->assign('textFiles', $textFiles);
        $this->view->assign('search', $search);
    }

    protected function copy(array $imagesToCopy, array $textFilesToCopy, int $numImages, int $numTextFiles): void
    {
        $folderOriginal = 't3docs';
        $folderActual = 't3docs-generated/actual';

        $pathOriginal = GeneralUtility::getFileAbsFileName($folderOriginal);
        $pathActual = GeneralUtility::getFileAbsFileName($folderActual);

        $urlActual = '/' . $folderActual;

        $files = GeneralUtility::removePrefixPathFromList(GeneralUtility::getAllFilesAndFoldersInPath(
            [], $pathActual . '/'
        ), $pathActual);

        $imageExtensionsIndex = array_flip($this->imageExtensions);
        $imagesToCopyIndex = array_flip($imagesToCopy);
        $textFilesToCopyIndex = array_flip($textFilesToCopy);

        $numCopiedImages = 0;
        $numCopiedFiles = 0;
        foreach ($files as $file) {
            $fileExtension = pathinfo($file, PATHINFO_EXTENSION);

            if (isset($imageExtensionsIndex[$fileExtension])) {
                $image = new File($pathActual . $file, $urlActual . $file);
                if (isset($imagesToCopyIndex[$image->getHash()])) {
                    $image->copy($pathOriginal . $file);
                    $numCopiedImages++;
                }
            } else {
                $textFile = new File($pathActual . $file, $urlActual . $file);
                if (isset($textFilesToCopyIndex[$textFile->getHash()])) {
                    $textFile->copy($pathOriginal . $file);
                    $numCopiedFiles++;
                }
            }
        }

        if ($numImages === 1) {
            $message = sprintf('%d of %d image ', $numCopiedImages, $numImages);
        } else {
            $message = sprintf('%d of %d images ', $numCopiedImages, $numImages);
        }
        if ($numCopiedFiles === 1) {
            $message .= sprintf('and %d of %d code snippet and reST include file copied.', $numCopiedFiles, $numTextFiles);
        } else {
            $message .= sprintf('and %d of %d code snippets and reST include files copied.', $numCopiedFiles, $numTextFiles);
        }
        $this->pushMessage($message, InfoboxViewHelper::STATE_OK);
    }

    /**
     * Add a message to the module internal message stack. They are displayed as infoboxes at a user-defined
     * position in the template. Survives redirects similar to $this->addFlashMessage().
     *
     * @param string $message
     * @param int $state An InfoboxViewHelper state.
     */
    protected function pushMessage(string $message, int $state): void
    {
        $moduleData = $this->getBackendUser()->getModuleData('tx_screenshots');
        $moduleData['messages'][] = ['message' => $message, 'state' => $state];
        $this->getBackendUser()->pushModuleData('tx_screenshots', $moduleData);
    }

    /**
     * Retrieve messages from the module internal message stack.
     *
     * @return array
     */
    protected function fetchMessages(): array
    {
        $messages = [];

        $moduleData = $this->getBackendUser()->getModuleData('tx_screenshots');
        if (isset($moduleData['messages'])) {
            $messages = $moduleData['messages'];
            unset($moduleData['messages']);
            $this->getBackendUser()->pushModuleData('tx_screenshots', $moduleData);
        }

        return $messages;
    }

    /**
     * @return BackendUserAuthentication
     */
    protected function getBackendUser(): BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'];
    }
}
