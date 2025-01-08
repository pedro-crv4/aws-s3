<?php

namespace Yampi\AwsS3;

require __DIR__ . '/../vendor/autoload.php';

use Aws\S3\S3Client;
use Aws\Lambda\LambdaClient;
use Aws\Exception\AwsException;

class S3Upload {
    private $client;
    private $lambdaClient;
    private $basePath;
    private $bucket;

    public function __construct()
    {
        $this->client = new S3Client([
            'version' => 'latest',
            'region' => 'us-east-1',
            'credentials' => [
                'key' => getenv('AWS_KEY'),
                'secret' => getenv('AWS_SECRET'),
            ],
        ]);

        $this->lambdaClient = new LambdaClient([
            'region' => 'us-east-1',
            'version' => 'latest',
            'credentials' => [
                'key' => getenv('AWS_LAMBDA_KEY'),
                'secret' => getenv('AWS_LAMBDA_SECRET'),
            ],
        ]);

        $this->bucket = 'codigo-aberto-sandbox-assets';

        $this->basePath = "rocket/assets/rocket-preview";
    }

    public function uploadOriginalFile($fileName, $fileContent)
    {
        $originalFilePath = "rocket/templates/rocket-preview/scss/{$fileName}";
        var_dump($originalFilePath);

        $this->uploadToS3($originalFilePath, $fileContent);
    }

    public function uploadToS3($filePath, $fileContent)
    {
        try {
            $result = $this->client->putObject([
                'Bucket' => $this->bucket,
                'Key'    => $filePath,
                'Body' => $fileContent,
                'ContentType' => 'application/javascript'
            ]);
            
            echo "Upload realizado com sucesso. URL do arquivo: " . $result['ObjectURL'] . "\n";
        } catch (AwsException $e) {
            echo "Erro ao fazer upload: " . $e->getMessage() . "\n";
        }
    }

    public function getFiles($directory)
    {
        $files = [];

        $items = scandir($directory);

        $items = array_diff($items, array('.', '..'));

        foreach ($items as $item) {
            $fullPath = $directory . DIRECTORY_SEPARATOR . $item;

            if (is_dir($fullPath)) {
                $files = array_merge($files, $this->getFiles($fullPath));
            } else {
                $files[] = $fullPath;
            }
        }

        return $files;
    }
}

$s3 = new S3Upload();

$directory = '/home/yampi/yampi/rocket/resources/scss';

$filesPath = $s3->getFiles($directory);

foreach ($filesPath as $key => $file) {
    $filePath = explode('/scss/', $file)[1];

    $s3->uploadOriginalFile($filePath, file_get_contents($file));
}
