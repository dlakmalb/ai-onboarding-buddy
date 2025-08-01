<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class GenerateQuestionEmbeddings extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'onboarding:generate-embeddings';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate embeddings for onboarding buddy questions';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $path = storage_path('app/onboarding/questions.json');

        $questions = json_decode(file_get_contents($path), true);

        $embeddings = [];

        $useFake = env('ONBOARDING_FAKE_MODE', false);

        foreach ($questions as $index => $qa) {
            if ($useFake) {
                $embedding = $this->fakeEmbedding();
                $this->info("FAKE Embedded: " . $qa['question']);
            } else {
                $embedding = $this->generateVertexEmbedding($qa['question']);

                if (!$embedding) {
                    $this->error("Failed to embed: {$qa['question']}");
                    continue;
                }

                $embeddings[] = [
                    'question' => $qa['question'],
                    'answer'   => $qa['answer'],
                    'embedding' => $embedding,
                ];

                $this->info("✅ Embedded: {$qa['question']}");
            }
        }

        $fullPath = storage_path('app/onboarding/embedded_questions.json');

        // dd($fullPath);
        file_put_contents($fullPath, json_encode($embeddings, JSON_PRETTY_PRINT));
        $this->info('✅ All embeddings saved.');
    }

    private function generateVertexEmbedding(string $text): ?array
    {
        $projectId = env('GCP_PROJECT_ID');
        $location = env('GCP_LOCATION', 'us-central1');
        $model = env('EMBEDDING_MODEL', 'text-embedding-005');

        $url = "https://{$location}-aiplatform.googleapis.com/v1/projects/{$projectId}/locations/{$location}/publishers/google/models/{$model}:predict";
        $accessToken = trim(shell_exec("gcloud auth application-default print-access-token"));

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$accessToken}",
            'Content-Type' => 'application/json',
        ])->post($url, [
            'instances' => [
                ['content' => $text],
            ],
        ]);

        return $response->successful()
            ? $response['predictions'][0]['embeddings']['values'] ?? null
            : null;
    }

    private function fakeEmbedding(): array
    {
        return array_map(fn () => mt_rand() / mt_getrandmax(), range(1, 1536)); // 1536 is the embedding size for text-embedding-3-small
    }
}
