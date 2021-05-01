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

        if (! $openedFile = fopen($zipFileName, 'wb+')) {
            throw new RuntimeException('File opening error');
        }

        $curl = curl_init("https://api.github.com/repos/$owner/$repo/actions/artifacts/$artifactId/zip");
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT => GithubUserAgent::USER_AGENT,
            CURLOPT_HTTPHEADER => ['Authorization: token ' . $token],
            CURLOPT_FILE => $openedFile
        ]);

        curl_exec($curl);
        curl_close($curl);
        fclose($openedFile);

        // if ($response === false) {
        //     throw new RuntimeException('Curl error' . curl_error($curl));
        // }

        // $jsonResponse = json_decode($response, true);

        // if (is_array($jsonResponse) && ! empty($jsonResponse['message'])) {
        //     $message = $jsonResponse['message'];

        //     if (
        //         $message === 'Must have admin rights to Repository.'
        //         || $message === 'Bad credentials'
        //     ) {
        //         throw new UnauthorizedException();
        //     }

        //     if ($message === 'Not Found') {
        //         throw new NotFoundException();
        //     }

        //     throw new UnknownException($message);
        // }


        $zip = new ZipArchive();

        if (! $zip->open($zipFileName)) {
            throw new RuntimeException('Failed to open zip ' . $zipFileName);
        }

        $artifactFiles = [];

        for ($zippedFileIndex = 0; $zippedFileIndex < $zip->numFiles; $zippedFileIndex++) {
            $zippedFileName = $zip->getNameIndex($zippedFileIndex);
            $extractedName = $artifactName . '-' . $zippedFileName;

            $zip->extractTo($cacheDir, $zippedFileName);
            $extractedFileName = $cacheDir . $extractedName;
            rename($cacheDir . $zippedFileName, $extractedFileName);
            
            $artifactFiles[] = $extractedFileName;
        }

        $zip->close();
        unlink($zipFileName);

        return $artifactFiles;
    }
}
