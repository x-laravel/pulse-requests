@php
    $highest = $readings->flatten()->max();
@endphp
<x-pulse::card :cols="$cols" :rows="$rows" :class="$class">
    <x-pulse::card-header
        name="Requests"
        x-bind:title="`Time: {{ number_format($time) }}ms; Run at: ${formatDate('{{ $runAt }}')};`"
        details="past {{ $this->periodForHumans() }}"
    >
        <x-slot:icon>
            <x-pulse::icons.rocket-launch />
        </x-slot:icon>
        <x-slot:actions>
            <div class="flex flex-wrap items-center gap-4">
                @foreach (['Informational' => 'rgba(107,114,128,0.5)', 'Successful' => '#10b981', 'Redirection' => '#3b82f6', 'Client Error' => '#eab308', 'Server Error' => '#e11d48'] as $label => $color)
                    <div class="flex items-center gap-2 text-xs text-gray-600 dark:text-gray-400 font-medium">
                        <div class="h-0.5 w-3 rounded-full" style="background-color: {{ $color }};"></div>
                        {{ $label }}
                    </div>
                @endforeach

                @if ($options !== [])
                    <x-pulse::select
                        wire:model.live="group"
                        id="select-pulse-requests-group"
                        label="Group"
                        :options="$options"
                    />
                @endif
            </div>
        </x-slot:actions>
    </x-pulse::card-header>

    <x-pulse::scroll :expand="$expand" wire:poll.5s="">
        @if ($readings->isEmpty())
            <x-pulse::no-results />
        @else
            <div class="w-full h-full relative">
                <div class="absolute left-1 top-1 z-10 max-w-fit h-4 flex items-center px-1 text-xs leading-none text-white font-bold bg-purple-500 rounded">
                    @if (($config['sample_rate'] ?? 1) < 1)
                        <span title="Sample rate: {{ $config['sample_rate'] }}, Raw value: {{ number_format($highest) }}">~{{ number_format($highest * (1 / $config['sample_rate'])) }}</span>
                    @else
                        {{ number_format($highest) }}
                    @endif
                </div>

                <div
                    wire:ignore
                    class="w-full h-full"
                    x-data="pulseRequestsChart({
                        readings: @js($readings),
                        sampleRate: {{ $config['sample_rate'] ?? 1 }},
                    })"
                >
                    <canvas x-ref="canvas" class="w-full h-full ring-1 ring-gray-900/5 dark:ring-gray-100/10 bg-gray-50 dark:bg-gray-800 rounded-md shadow-sm"></canvas>
                </div>
            </div>
        @endif
    </x-pulse::scroll>
</x-pulse::card>

@script
<script>
Alpine.data('pulseRequestsChart', (config) => ({
    series: [
        { type: 'informational', label: 'Informational', color: 'rgba(107,114,128,0.5)', fill: ['rgba(107,114,128,0.12)', 'rgba(107,114,128,0.02)'] },
        { type: 'successful', label: 'Successful', color: '#10b981', fill: ['rgba(16,185,129,0.12)', 'rgba(16,185,129,0.02)'] },
        { type: 'redirection', label: 'Redirection', color: '#3b82f6', fill: ['rgba(59,130,246,0.12)', 'rgba(59,130,246,0.02)'] },
        { type: 'client_error', label: 'Client Error', color: '#eab308', fill: ['rgba(234,179,8,0.12)', 'rgba(234,179,8,0.02)'] },
        { type: 'server_error', label: 'Server Error', color: '#e11d48', fill: ['rgba(225,29,72,0.12)', 'rgba(225,29,72,0.02)'] },
    ],
    init() {
        const chart = new Chart(
            this.$refs.canvas,
            {
                type: 'line',
                data: {
                    labels: this.labels(config.readings),
                    datasets: this.series.map((series, index) => ({
                        label: series.label,
                        borderColor: series.color,
                        data: this.scale(config.readings[series.type]),
                        order: this.series.length - index,
                        fill: true,
                        backgroundColor: (context) => {
                            const { ctx, chartArea } = context.chart

                            if (! chartArea) {
                                return
                            }

                            return this.gradient(ctx, chartArea, series.fill[0], series.fill[1])
                        },
                    })),
                },
                options: {
                    maintainAspectRatio: false,
                    layout: {
                        autoPadding: false,
                        padding: {
                            top: 24,
                        },
                    },
                    datasets: {
                        line: {
                            borderWidth: 2,
                            borderCapStyle: 'round',
                            pointHitRadius: 10,
                            pointStyle: false,
                            tension: 0.2,
                            segment: {
                                borderColor: (ctx) => ctx.p0.raw === 0 && ctx.p1.raw === 0 ? 'transparent' : undefined,
                            },
                        },
                    },
                    scales: {
                        x: {
                            display: false,
                        },
                        y: {
                            display: false,
                            min: 0,
                        },
                    },
                    plugins: {
                        legend: {
                            display: false,
                        },
                        tooltip: {
                            mode: 'index',
                            position: 'nearest',
                            intersect: false,
                            callbacks: {
                                beforeBody: (context) => context
                                    .filter(item => item.raw > 0)
                                    .map(item => `${item.dataset.label}: ${config.sampleRate < 1 ? '~' : ''}${item.formattedValue}`)
                                    .join(', '),
                                label: () => null,
                            },
                        },
                    },
                },
            }
        )

        Livewire.on('pulse-requests-chart-update', ({ readings }) => {
            chart.data.labels = this.labels(readings)

            this.series.forEach((series, index) => {
                chart.data.datasets[index].data = this.scale(readings[series.type])
            })

            chart.update()
        })
    },
    labels(readings) {
        const series = this.series.find(series => readings[series.type] !== undefined)

        return series ? Object.keys(readings[series.type]).map(formatDate) : []
    },
    scale(data) {
        return Object.values(data ?? {}).map(value => value * (1 / config.sampleRate))
    },
    gradient(ctx, chartArea, fromColor, toColor) {
        const gradient = ctx.createLinearGradient(0, chartArea.bottom, 0, chartArea.top)
        gradient.addColorStop(1, fromColor)
        gradient.addColorStop(0, toColor)

        return gradient
    },
}))
</script>
@endscript
