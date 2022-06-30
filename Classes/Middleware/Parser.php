<?php

namespace Archriss\ArcParsercontent\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Http\Stream;
use TYPO3\CMS\Core\Utility\GeneralUtility;

ini_set('pcre.backtrack_limit', 1000000000);

/**
 * Parsercontent processing:
 * all found words in content wich correspond with the parsercontent entries
 * will be enriched with special accessibility markup and link to the parsercontent
 */
class Parser implements MiddlewareInterface
{

    /**
     * Type of files to parse
     * @var array
     */
    protected $typeFileParse = [
        'avi' => 'file-video',
        'doc' => 'file-doc',
        'docx' => 'file-doc',
        'eps' => 'download',
        'gif' => 'image',
        'jpeg' => 'image',
        'jpg' => 'image',
        'mp3' => 'download',
        'mp4' => 'file-video',
        'odt' => 'file-doc',
        'pdf' => 'file-pdf',
        'png' => 'image',
        'ppt' => 'download',
        'pptx' => 'download',
        'txt' => 'file-doc',
        'xls' => 'file-xls',
        'xlsx' => 'file-xls',
        'zip' => 'download',
    ];

    /**
     * File size naming convention
     * @var string 
     */
    protected $fileSizeUnits = ' o| ko| mo| go| to| po| eo| zo| yo';

    /**
     * Process an incoming server request.
     *
     * Processes an incoming server request in order to produce a response.
     * If unable to produce the response itself, it may delegate to the provided
     * request handler to do so.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request
     * @param \Psr\Http\Server\RequestHandlerInterface $handler
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException
     * @throws \TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);
        if (
            GeneralUtility::makeInstance(ExtensionConfiguration::class)
                ->get('arc_parsercontent', 'enable/parsing') == 1
        ) {
            $this->fileSizeUnits = GeneralUtility::makeInstance(ExtensionConfiguration::class)
                ->get('arc_parsercontent', 'format/fileSizeUnits') ?: $this->fileSizeUnits;
            $body = $response->getBody();
            $body->rewind();
            $response = $response->withBody(
                $this->getNewBody(
                    $this->parseFile(
                        $this->parseContent(
                            $body->getContents()
                        )
                    )
                )
            );
        }
        return $response;
    }

    /**
     * @param string $content
     * @return \TYPO3\CMS\Core\Http\Stream
     */
    protected function getNewBody($content)
    {
        $body = new Stream('php://temp', 'rw');
        $body->write($content);
        return $body;
    }

    /**
     * @param string $content
     * @return string
     */
    protected function parseContent($content)
    {
        // Patern used to find all RTE occurency
        $partsPattern = '/(<!--ARCPARSER_begin-->)(.*?)(<!--ARCPARSER_end-->)/si';
        preg_match_all($partsPattern, $content, $tempParts);
        $originalParts = $transformedParts = $tempParts[0];
        $originalPartsCount = count($originalParts);

        // Parse all found parts
        for ($i = 0; $i < $originalPartsCount; $i++) {
            // Files link
            foreach ($this->typeFileParse as $extFile => $icon) {
                $patternFile = '/([a-zA-Z0-9-_\\/\\.]+\\.' . $extFile . ')/is';
                preg_match_all($patternFile, $transformedParts[$i], $matches, PREG_SET_ORDER);
                if (count($matches)) {
                    foreach (self::uniquify($matches) as $file) {
                        if (isset($file[0]) && is_file($_SERVER['DOCUMENT_ROOT'] . $file[0])) {
                            $size = $this->fileSize($_SERVER['DOCUMENT_ROOT'] . $file[0]);
                            $patternUri = '#<a href="' . $file[0] . '"(.*)>(.*)</a>#Us';
                            $transformedParts[$i] = preg_replace(
                                $patternUri,
                                '<a href="' . $file[0] . '"$1><i class="icon-' . $icon . '"></i><span>$2 (' . strtolower($extFile). ' - ' . $size . ')</span></a>',
                                $transformedParts[$i]
                            );
                        }
                    }
                }
            }

            // Mailto link
            $patternUri = '#<a href="mailto:(.*)"(.*)>(.*)</a>#Us';
            $transformedParts[$i] = preg_replace(
                $patternUri,
                '<a href="mailto:$1"$2><i class="icon-envelope"></i><span>$3</span></a>',
                $transformedParts[$i]
            );

            // Encrypted mailto link
            $patternUri = '#<a href="javascript:linkTo_UnCryptMailto(.*)"(.*)>(.*)</a>#Us';
            $transformedParts[$i] = preg_replace(
                $patternUri,
                '<a href="javascript:linkTo_UnCryptMailto$1"$2><i class="icon-envelope"></i><span>$3</span></a>',
                $transformedParts[$i]
            );

            // External link
            $patternUri = '#<a href="(https?)://(.*)"(.*)>(.*)</a>#Us';
            $transformedParts[$i] = preg_replace(
                $patternUri,
                '<a href="$1://$2"$3><i class="icon-link-external"></i><span>$4</span></a>',
                $transformedParts[$i]
            );

            // internal link
            $patternUri = '#<a href="/(.*)"(.*)target="_self"(.*)>(.*)</a>#Us'; // internal link should always have target _self
            $transformedParts[$i] = preg_replace(
                $patternUri,
                '<a href="/$1"$2target="_self"$3><i class="icon-link-internal"></i><span>$4</span></a>',
                $transformedParts[$i]
            );
        }

        return str_replace($originalParts, $transformedParts, $content, $count);
    }

    /**
     * @param string $content
     * @return string
     */
    protected function parseFile($content)
    {
        // Patern used to find all RTE occurency
        $partsPattern = '/(<!--FILEPARSER_begin-->)(.*?)(<!--FILEPARSER_end-->)/si';
        preg_match_all($partsPattern, $content, $tempParts);
        $originalParts = $transformedParts = $tempParts[0];
        $originalPartsCount = count($originalParts);

        // Parse all found parts
        for ($i = 0; $i < $originalPartsCount; $i++) {
            // Files link
            foreach ($this->typeFileParse as $extFile => $icon) {
                $patternFile = '/([a-zA-Z0-9-_\\/\\.]+\\.' . $extFile . ')/is';
                preg_match_all($patternFile, $transformedParts[$i], $matches, PREG_SET_ORDER);
                if (count($matches)) {
                    foreach (self::uniquify($matches) as $file) {
                        if (isset($file[0]) && is_file($_SERVER['DOCUMENT_ROOT'] . $file[0])) {
                            $size = $this->fileSize($_SERVER['DOCUMENT_ROOT'] . $file[0]);
                            $patternUri = '#<a href="' . $file[0] . '"(.*)>(.*)</a>#Us';
                            $transformedParts[$i] = preg_replace(
                                $patternUri,
                                '<a href="' . $file[0] . '"$1>$2 (' . strtolower($extFile). ' - ' . $size . ')</a>',
                                $transformedParts[$i]
                            );
                        }
                    }
                }
            }
        }

        return str_replace($originalParts, $transformedParts, $content, $count);
    }

    /**
     * @param string $file
     * @return mixed
     */
    protected function fileSize(string $file)
    {
        return GeneralUtility::formatSize(
            filesize($file),
            $this->fileSizeUnits
        );
    }

    /**
     * @param array $array
     * @return array
     */
    protected static function uniquify(array $array)
    {
        return array_reduce(
            $array,
            function ($carry, $item) {
                if (!in_array($item, $carry)) {
                    $carry[] = $item;
                }
                return $carry;
            },
            []
        );
    }
}