<?php

namespace PierreMiniggio\GithubActionArtifactDownloader;

use PierreMiniggio\GithubActionArtifactDownloader\Exception\NotFoundException;
use PierreMiniggio\GithubActionArtifactDownloader\Exception\UnauthorizedException;
use PierreMiniggio\GithubActionArtifactDownloader\Exception\UnknownException;
use PierreMiniggio\GithubUserAgent\GithubUserAgent;
use RuntimeException;
use ZipArchive;

class GithubActionArtifactDownloader
{

    /**
     * @return string[] artifacts' file paths
     * 
     * @throws NotFoundException
     * @throws RuntimeException
     * @throws UnauthorizedException
     */
    public function download(
        string $token,
        string $owner,
        string $repo,
        int $artifactId
    ): array
    {
        $curl = curl_init("https://api.github.com/repos/$owner/$repo/actions/artifacts/$artifactId/zip");
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT => GithubUserAgent::USER_AGENT,
            CURLOPT_HTTPHEADER => ['Authorization: token ' . $token]
        ]);

        $response = curl_exec($curl);

        if ($response === false) {
            curl_close($curl);
            throw new RuntimeException('Curl error' . curl_error($curl));
        }

        $jsonResponse = json_decode($response, true);

        if (is_array($jsonResponse) && ! empty($jsonResponse['message'])) {
            $message = $jsonResponse['message'];

            if (
                $message === 'Must have admin rights to Repository.'
                || $message === 'Bad credentials'
            ) {
                curl_close($curl);
                throw new UnauthorizedException();
            }

            if ($message === 'Not Found') {
                curl_close($curl);
                throw new NotFoundException();
            }

            curl_close($curl);
            throw new UnknownException($message);
        }

        curl_close($curl);

        $cacheDir =
            __DIR__
            . DIRECTORY_SEPARATOR
            . '..'
            . DIRECTORY_SEPARATOR
            . 'cache'
            . DIRECTORY_SEPARATOR
        ;
        
        if (! file_exists($cacheDir)) {
            mkdir($cacheDir);
        }

        $artifactName = 'artifact-' . $artifactId;
        $zipName = $artifactName . '.zip';
        $zipFileName = $cacheDir . $zipName;

        if (file_exists($zipFileName)) {
            unlink($zipFileName);
        }

        $file = fopen($zipFileName, 'w+');
        fputs($file, $response);
        fclose($file);

        $zip = new ZipArchive();

        if (! $zip->open($zipFileName)) {
            throw new RuntimeException('Failed to open zip ' . $zipFileName);
        }

        $artifactFiles = [];

        for ($zippedFileIndex = 0; $zippedFileIndex < $zip->numFiles; $zippedFileIndex++) {
            $zippedFileName = $zip->getNameIndex($zippedFileIndex);
            $extractedName = $artifactName . '-' . $zippedFileName;

            if ($zippedFileData = $zip->getFromIndex($zippedFileIndex)) {
                $extractedFileName = $cacheDir . $extractedName;

                if (file_exists($extractedFileName)) {
                    unlink($extractedFileName);
                }

                if (file_put_contents($extractedFileName, $zippedFileData)) {
                    $artifactFiles[] = $extractedFileName;
                }
            }
        }

        $zip->close();
        unlink($zipFileName);

        return $artifactFiles;
    }
}
