<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-10">
                <div class="grid grid-cols-1 lg:grid-cols-1 gap-6">
                    <!-- Left Column: Chart -->
                    <div class="bg-white rounded-2xl border shadow-sm p-4">
                        <h2 class="mb-4 text-sm uppercase tracking-wider text-gray-500 text-center">
                            24-Hour Sentiment Distribution
                        </h2>

                        <div x-data="sentimentChart()" x-init="initChart()" class="relative h-64">
                            <canvas id="sentimentPie"></canvas>
                        </div>
                    </div>

                    <div class="bg-white rounded-2xl border shadow-sm p-4">
                        <h2 class="mb-4 text-sm uppercase tracking-wider text-gray-500 text-center">
                            Sentiment Timeline (24 Hours)
                        </h2>

                        <div class="relative h-64">
                            <canvas id="sentimentLine"></canvas>
                        </div>
                    </div>


                    <!-- Right Column: Content -->
                    <div class="w-full">
                        <h2 class="mb-10 bg-indigo-400 text-white px-2 py-2 text-center rounded-lg text-lg font-bold">
                            Tabular Presentation</h2>


                        <div class="relative overflow-x-auto rounded-2xl border shadow-sm">
                            <table class="w-full text-sm text-left rtl:text-right text-gray-500">
                                <thead class="text-xs text-gray-700 uppercase bg-gray-300">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-lg font-bold">
                                            Sentiment
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-lg font-bold">
                                            Time Of Analysis
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($sentiments->sortByDesc('created_at') as $sentiment)
                                        <tr class="odd:bg-white even:bg-gray-50 border-b border-gray-200">
                                            <th scope="row"
                                                class="px-6 py-4 font-medium text-gray-900 whitespace-nowrap">
                                                {{ ucfirst($sentiment->sentiment) }}
                                            </th>
                                            <td class="px-6 py-4">
                                                {{ $sentiment->created_at->format('d-m-Y | h:i A') }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                            <div class="mt-5">
                                {{ $sentiments->links() }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function sentimentChart() {
            return {
                pieChart: null,
                lineChart: null,
                chartData: @json($chartData),
                hours: @json($hours),

                initChart() {
                    this.renderPie();
                    this.renderLine();
                },

                renderPie() {
                    const ctx = document.getElementById('sentimentPie').getContext('2d');

                    const totals = {};
                    Object.keys(this.chartData).forEach(sentiment => {
                        totals[sentiment] = this.chartData[sentiment].reduce((a, b) => a + b, 0);
                    });

                    this.pieChart = new Chart(ctx, {
                        type: 'doughnut',
                        data: {
                            labels: Object.keys(totals),
                            datasets: [{
                                data: Object.values(totals),
                                backgroundColor: [
                                    '#3B82F6', // blue
                                    '#EF4444', // red
                                    '#10B981', // green
                                    '#F59E0B', // yellow
                                    '#8B5CF6', // purple
                                ],
                                borderWidth: 2
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            cutout: '65%',
                            plugins: {
                                legend: {
                                    position: 'bottom',
                                    labels: {
                                        boxWidth: 10
                                    }
                                }
                            }
                        }
                    });
                },

                renderLine() {
                    const ctx = document.getElementById('sentimentLine').getContext('2d');

                    const datasets = Object.keys(this.chartData).map((sentiment, index) => ({
                        label: sentiment,
                        data: this.chartData[sentiment],
                        borderWidth: 2,
                        tension: 0.4,
                        fill: false,
                    }));

                    this.lineChart = new Chart(ctx, {
                        type: 'line',
                        data: {
                            labels: this.hours.map(h => `${h}:00`),
                            datasets
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    position: 'bottom',
                                    labels: {
                                        font: {
                                            size: 11
                                        }
                                    }
                                }
                            },
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    ticks: {
                                        precision: 0
                                    }
                                }
                            }
                        }
                    });
                }
            }
        }
    </script>

</x-app-layout>
