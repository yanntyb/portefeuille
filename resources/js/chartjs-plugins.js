window.filamentChartJsGlobalPlugins ??= []
window.filamentChartJsGlobalPlugins.push({
    id: 'forceLegendLeft',
    beforeInit(chart) {
        if (chart.options.plugins?.legend) {
            chart.options.plugins.legend.position = 'left'
        }
    },
})
