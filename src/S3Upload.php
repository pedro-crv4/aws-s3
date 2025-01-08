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

        $this->basePath = "minhasuperloja/assets/rocket-preview";
    }

    public function uploadOriginalFile($fileName, $fileContent)
    {
        $originalFileName = str_replace('.js', '.vue', $fileName);
        $originalFilePath = "minhasuperloja/templates/rocket-preview/components/{$originalFileName}";
        var_dump($originalFilePath);

        $this->uploadToS3($originalFilePath, $fileContent);
    }

    public function uploadBuildedFile($fileName, $fileContent)
    {
        $buildedFilePath = "{$this->basePath}/components/{$fileName}";
        var_dump($buildedFilePath);

        $this->uploadToS3($buildedFilePath, $fileContent);
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

    public function writeBuilded($filePath, $fileContent)
    {
        $path = "/home/yampi/Desktop/dev/aws-s3/components/$filePath";
        
        $content = $fileContent;
    
        $file = fopen($path, 'w');
    
        if ($file) {
            fwrite($file, $content);
            fclose($file);
        } else {
            echo "Erro ao abrir o arquivo.";
        }
    }

    public function buildFile($params)
    {
        $response = $this->lambdaClient->invoke([
            'FunctionName' => 'AssetBuilderFunction-sandbox',
            'Payload' => json_encode($params),
            'LogType' => 'None',
        ]);

        $response = json_decode($response['Payload']->getContents());

        if(isset($response->statusCode) && $response->statusCode == 200) {
            return json_decode($response->body);
        }

        return null;
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

$directory = '/home/yampi/yampi/yampi-templates/rocket/components';

$filesPath = $s3->getFiles($directory);

// $filesPath = [
//     '/home/yampi/yampi/yampi-templates/rocket/components/MiniCart.vue',
// ];

foreach ($filesPath as $key => $file) {
    $fileToBuild = [
        'name' => basename($file),
        'content' => file_get_contents($file)
    ];
    
    $response = $s3->buildFile([
        'action' => 'componentBuilder',
        'fileName' => $fileToBuild['name'],
        'code' => $fileToBuild['content'],
    ]);

    $filePath = explode('/components/', $file)[1];
    $filePath = str_replace('.vue', '.js', $filePath);

    if ($response) {
        $s3->writeBuilded($filePath, $response->code);
        // $s3->uploadOriginalFile($filePath, $fileToBuild['content']);
        // $s3->uploadBuildedFile($filePath, $response->code);
    } else {
        $errorsFile = 'errors.txt';
    
        $content = "$file\n";
    
        $file = fopen($errorsFile, 'a');
    
        if ($file) {
            fwrite($file, $content);
            fclose($file);
            echo "Conteúdo adicionado com sucesso!";
        } else {
            echo "Erro ao abrir o arquivo.";
        }
    }
}
