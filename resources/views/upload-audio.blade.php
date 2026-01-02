<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Upload Audio') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if (session('success'))
                <div x-data="{ show: true }" x-show="show"
                    class="flex items-center justify-between px-3 py-2 bg-green-500 mb-10 shadow-sm sm:rounded-lg text-white/80 text-lg font-bold">
                    <span>{{ session('success') }}</span>
                    <button @click="show = false" class="ml-4 text-white hover:text-gray-200">
                        ✕
                    </button>
                </div>
            @endif

            @if (session('error'))
                <div x-data="{ show: true }" x-show="show"
                    class="flex items-center justify-between px-3 py-2 bg-red-500 mb-10 shadow-sm sm:rounded-lg text-white/80 text-lg font-bold">
                    <span>{{ session('error') }}</span>
                    <button @click="show = false" class="ml-4 text-white hover:text-gray-200">
                        ✕
                    </button>
                </div>
            @endif

            <div x-data="uploadForm()" class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                {{-- Success/Error Alerts --}}
                <template x-if="message">
                    <div class="flex items-center justify-between px-3 py-2 mb-6 shadow-sm sm:rounded-lg text-white/80 text-lg font-bold"
                        :class="success ? 'bg-green-500' : 'bg-red-500'">
                        <span x-text="message"></span>
                        <button @click="message = ''" class="ml-4 text-white hover:text-gray-200">✕</button>
                    </div>
                </template>

                {{-- Upload Form --}}
                <form @submit.prevent="submit">
                    <div class="flex items-center justify-center w-full">
                        <label for="dropzone-file"
                            class="flex flex-col items-center justify-center w-full h-64 p-6 border-2 border-gray-300 border-dashed rounded-lg cursor-pointer bg-gray-50 hover:bg-gray-100">
                            <div class="flex flex-col items-center justify-center pt-5 pb-6">
                                <svg class="w-8 h-8 mb-4 text-gray-500" xmlns="http://www.w3.org/2000/svg"
                                    fill="none" viewBox="0 0 20 16">
                                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M13 13h3a3 3 0 0 0 0-6h-.025A5.56 5.56 0 0 0 16 6.5 5.5 5.5 0 0 0 5.207 5.021C5.137 5.017 5.071 5 5 5a4 4 0 0 0 0 8h2.167M10 15V6m0 0L8 8m2-2 2 2" />
                                </svg>
                                <p class="mb-2 text-sm text-gray-500">
                                    <span class="font-semibold">Click to upload</span> or drag and drop
                                </p>
                                <p class="text-xs text-gray-500">Audio File (MP3, WAV)</p>
                            </div>
                            <input id="dropzone-file" type="file" name="file" accept=".mp3,.wav,audio/*"
                                class="hidden" @change="file = $event.target.files[0]" />
                        </label>
                    </div>

                    <div class="mt-4 flex justify-center">
                        <button type="submit"
                            class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed"
                            :disabled="loading || !file">
                            <span x-show="!loading">Upload Audio</span>
                            <span x-show="loading" class="flex items-center">
                                <svg class="animate-spin h-5 w-5 mr-2 text-white" xmlns="http://www.w3.org/2000/svg"
                                    fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10"
                                        stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
                                </svg>
                                Analyzing...
                            </span>
                        </button>
                    </div>
                </form>

                <template x-if="sentiment">
                    <div class="mt-8 space-y-6">

                        <!-- FINAL EMOTION -->
                        <div class="text-center">
                            <h3 class="text-2xl font-bold text-gray-800">
                                Detected Emotion
                            </h3>
                            <p class="mt-1 text-3xl font-bold text-blue-600 tracking-wide">
                                <span x-text="sentiment"></span>
                            </p>
                        </div>

                        <!-- CONFIDENCE BARS -->
                        <div class="bg-gray-50 p-6 rounded-xl border">
                            <h4 class="text-lg font-semibold mb-4 text-gray-800">
                                Confidence Breakdown
                            </h4>

                            <template x-for="(value, emotion) in probabilities" :key="emotion">
                                <div class="mb-3">
                                    <div class="flex justify-between text-sm mb-1 text-gray-700">
                                        <span x-text="emotion"></span>
                                        <span x-text="(value * 100).toFixed(1) + '%'"></span>
                                    </div>

                                    <div class="w-full bg-gray-200 rounded">
                                        <div class="h-2 bg-blue-600 rounded" :style="`width: ${value * 100}%`">
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>

                        <!-- CHARTS -->
                        <div class="grid md:grid-cols-2 gap-6">

                            <!-- BAR CHART -->
                            <div class="bg-white p-4 rounded-2xl border shadow-sm">
                                <h4 class="text-sm font-semibold text-gray-700 mb-2">
                                    Emotion Confidence
                                </h4>

                                <div class="relative h-56"> <!-- 👈 CONTROLS HEIGHT -->
                                    <canvas id="barChart"></canvas>
                                </div>
                            </div>

                            <!-- DOUGHNUT CHART -->
                            <div class="bg-white p-4 rounded-2xl border shadow-sm">
                                <h4 class="text-sm font-semibold text-gray-700 mb-2">
                                    Emotion Distribution
                                </h4>

                                <div class="relative h-56 flex items-center justify-center">
                                    <canvas id="doughnutChart"></canvas>
                                </div>
                            </div>

                        </div>


                    </div>
                </template>


            </div>
        </div>
    </div>

    <script>
        function uploadForm() {
            return {
                file: null,
                loading: false,
                message: '',
                success: false,

                sentiment: null,
                probabilities: null,

                barChart: null,
                doughnutChart: null,

                async submit() {
                    if (!this.file) return;

                    this.loading = true;
                    this.message = '';
                    this.sentiment = null;
                    this.probabilities = null;

                    let formData = new FormData();
                    formData.append('file', this.file);

                    try {
                        let response = await fetch("{{ route('upload-audio.post') }}", {
                            method: "POST",
                            headers: {
                                "X-CSRF-TOKEN": "{{ csrf_token() }}"
                            },
                            body: formData
                        });

                        let result = await response.json();
                        console.log("API RESULT:", result);


                        if (!response.ok) throw result;

                        this.success = true;
                        this.message = "Audio analyzed successfully";
                        this.sentiment = result.sentiment;
                        this.probabilities = result.probabilities;

                        // this.$nextTick(() => this.renderCharts());
                        if (result.probabilities) {
                            this.probabilities = result.probabilities;
                            this.$nextTick(() => this.renderCharts());
                        } else {
                            console.error("Probabilities missing:", result);
                        }


                    } catch (e) {
                        this.success = false;
                        this.message = "Analysis failed";
                    } finally {
                        this.loading = false;
                    }
                },

                renderCharts() {
                    if (!this.probabilities || Object.keys(this.probabilities).length === 0) {
                        console.error("No probabilities to render");
                        return;
                    }

                    const labels = Object.keys(this.probabilities);
                    const values = Object.values(this.probabilities).map(v => v * 100);

                    this.barChart?.destroy();
                    this.doughnutChart?.destroy();

                    this.barChart = new Chart(
                        document.getElementById('barChart'), {
                            type: 'bar',
                            data: {
                                labels,
                                datasets: [{
                                    data: values,
                                    backgroundColor: '#2563eb',
                                    borderRadius: 6,
                                    barThickness: 24, // 👈 thinner bars
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false, // 👈 KEY FIX
                                plugins: {
                                    legend: {
                                        display: false
                                    }
                                },
                                scales: {
                                    y: {
                                        beginAtZero: true,
                                        max: 100,
                                        ticks: {
                                            font: {
                                                size: 11
                                            }
                                        }
                                    },
                                    x: {
                                        ticks: {
                                            font: {
                                                size: 11
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    );


                    this.doughnutChart = new Chart(
                        document.getElementById('doughnutChart'), {
                            type: 'doughnut',
                            data: {
                                labels,
                                datasets: [{
                                    data: values,
                                    backgroundColor: [
                                        '#ef4444', '#f97316', '#eab308',
                                        '#22c55e', '#3b82f6', '#a855f7'
                                    ],
                                    borderWidth: 2
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false, // 👈 KEY FIX
                                cutout: '70%', // 👈 thinner ring
                                plugins: {
                                    legend: {
                                        position: 'bottom',
                                        labels: {
                                            boxWidth: 10,
                                            font: {
                                                size: 11
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    );

                }

            }
        }
    </script>

</x-app-layout>
