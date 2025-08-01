<?php

namespace App\Http\Controllers;

use Gemini\Laravel\Facades\Gemini;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class OnboardingBuddyController extends Controller
{
    public function ask(Request $request)
    {
        $question = $request->input('question');

        // Load stored embedded questions
        $path = storage_path('app/onboarding/embedded_questions.json');
        $stored = json_decode(file_get_contents($path), true);

        // Generate embedding for user question
        $userEmbedding = $this->generateVertexEmbedding($question);

        // If embedding failed, return fallback
        if (!$userEmbedding || !is_array($userEmbedding)) {
            return response()->json([
                'answer' => "Sorry, I couldn't process your question at the moment.",
                'match' => null,
                'score' => null,
            ], 500);
        }

        // Find best match using cosine similarity
        $best = null;
        $highestScore = -1;

        foreach ($stored as $entry) {
            if (!isset($entry['embedding']) || !is_array($entry['embedding'])) {
                continue;
            }

            $score = $this->cosineSimilarity($userEmbedding, $entry['embedding']);

            if ($score > $highestScore) {
                $highestScore = $score;
                $best = $entry;
            }
        }

        // Compose prompt for Gemini
        $prompt = "You are a helpful assistant for the Fixico Operations Dashboard.\n\n"
                . "Relevant Instruction:\n" . ($best['answer'] ?? 'N/A') . "\n\n"
                . "User Question:\n" . $question . "\n\n"
                . "Answer:";

        $geminiAnswer = null; // $this->generateGeminiResponse($prompt);

        return response()->json([
            'answer' => $geminiAnswer ?? ($best['answer'] ?? 'No relevant answer found.'),
            'match' => $best['question'] ?? null,
            'score' => $highestScore,
        ]);
    }

    private function cosineSimilarity(array $a, array $b): float
    {
        $dotProduct = array_sum(array_map(fn($x, $y) => $x * $y, $a, $b));
        $magnitudeA = sqrt(array_sum(array_map(fn($x) => $x * $x, $a)));
        $magnitudeB = sqrt(array_sum(array_map(fn($x) => $x * $x, $b)));

        return $magnitudeA && $magnitudeB ? $dotProduct / ($magnitudeA * $magnitudeB) : 0;
    }

    private function generateVertexEmbedding(string $text): ?array
    {
        $projectId = env('GCP_PROJECT_ID');
        $location = env('GCP_LOCATION', 'us-central1');
        $model = env('EMBEDDING_MODEL', 'text-embedding-005');

        $url = "https://{$location}-aiplatform.googleapis.com/v1/projects/{$projectId}/locations/{$location}/publishers/google/models/{$model}:predict";
        $gcloud = '/Users/dhananjaya/google-cloud-sdk/bin/gcloud';
        $accessToken = trim(shell_exec("$gcloud auth application-default print-access-token"));
        $response = Http::withToken($accessToken)
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post($url, ['instances' => [['content' => $text]]]);

        return $response->successful()
            ? $response['predictions'][0]['embeddings']['values'] ?? null
            : null;
    }

    private function generateGeminiResponse(string $prompt): ?string
    {
        $projectId = env('GCP_PROJECT_ID');
        $region = env('GCP_LOCATION', 'us-central1');
        $model = env('GEMINI_MODEL_ID', 'text-embedding-005');

        $url = "https://{$region}-aiplatform.googleapis.com/v1/projects/{$projectId}/locations/{$region}/publishers/google/models/{$model}:predict";

        $accessToken = trim(shell_exec("gcloud auth application-default print-access-token"));

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$accessToken}",
            'Content-Type' => 'application/json',
        ])->post($url, [
            'instances' => [
                ['prompt' => $prompt],
            ],
            'parameters' => [
                'temperature' => 0.3,
            ],
        ]);

        return $response['predictions'][0]['content'] ?? null;
    }
}


