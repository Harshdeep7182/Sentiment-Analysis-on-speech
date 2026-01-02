<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\View\View;
use Illuminate\Support\Facades\Auth;
use App\Models\Sentiment;
use Carbon\Carbon;


class DashboardController extends Controller
{
    public function dashboard(): View
    {
        $today = Carbon::today();

        // Fetch sentiments of the authenticated user for today
        $sentiments = Auth::user()->sentiments()
            ->whereDate('created_at', $today)
            ->paginate(10);

        // Prepare 24-hour range
        $hours = range(0, 23);

        // Get unique sentiment types
        $sentimentTypes = $sentiments->pluck('sentiment')->unique();
        $chartData = [];

        // Initialize chart data
        foreach ($sentimentTypes as $type) {
            $chartData[$type] = array_fill(0, 24, 0); // 24 zeros for 24 hours
        }

        // Count per hour
        foreach ($sentiments as $row) {
            $hour = $row->created_at->hour; // get hour from created_at
            $chartData[$row->sentiment][$hour] += 1; // increment count
        }

        return view('dashboard', [
            'sentiments' => $sentiments,
            'chartData' => $chartData,
            'hours' => $hours
        ]);
    }


    public function uploadAudio() : View{
        return view('upload-audio');
    }

    public function uploadAudioPost(Request $request)
    {
        // Validate input (only mp3/wav)
        $request->validate([
            'file' => 'required|mimes:mp3,wav|max:10240', // max 10MB
        ]);

        $file = $request->file('file');

        // Generate timestamped name
        $timestampedName = now()->format('Ymd_His') . '_' . $file->getClientOriginalName();

        try {
            // Forward file to FastAPI server
            $response = Http::attach(
                'file', fopen($file->getRealPath(), 'r'), $timestampedName
            )->post('http://127.0.0.1:9000/upload');

            if ($response->successful()) {
                $result = $response->json();

                $data = [
                    'user_id'    => Auth::user()->id,
                    'audio_file' => $timestampedName,
                    'sentiment'  => $result['sentiment'] ?? '',
                ];

                Sentiment::create($data);

                return response()->json([
                    'success' => true,
                    'message' => "Sentiment detected: " . ucfirst($data['sentiment']),
                    'sentiment' => $data['sentiment'],
                    'probabilities' => $result['probabilities'] ?? [],
                ]);

            }

            return response()->json([
                'success' => false,
                'message' => 'File uploaded but Python server responded with an error.',
            ], 500);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Could not connect to the Python server. Please make sure it is running.',
            ], 500);
        }
    }
}
