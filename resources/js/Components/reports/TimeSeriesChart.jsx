import ChartTooltip from '@/Components/reports/ChartTooltip';
import { CHART_COLORS } from '@/Utils/reports';
import {
    Area,
    CartesianGrid,
    ComposedChart,
    Line,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';

/**
 * One parameter over time. The solid line is the aggregation that suits the
 * parameter (total for rainfall, average for temperature and streamflow); the
 * dashed lines carry the minimum and maximum of the same period so a mean never
 * hides an extreme.
 */
export default function TimeSeriesChart({ points = [], unit = null, decimals = 2, height = 300 }) {
    return (
        <ResponsiveContainer width="100%" height={height}>
            <ComposedChart data={points} margin={{ top: 8, right: 12, bottom: 0, left: -8 }}>
                <CartesianGrid strokeDasharray="3 3" stroke={CHART_COLORS.grid} vertical={false} />
                <XAxis
                    dataKey="label"
                    tick={{ fontSize: 10, fill: '#64748b' }}
                    tickLine={false}
                    axisLine={false}
                    minTickGap={28}
                />
                <YAxis
                    tick={{ fontSize: 10, fill: '#64748b' }}
                    tickLine={false}
                    axisLine={false}
                    width={58}
                    tickFormatter={(value) => Number(value).toLocaleString()}
                />
                <Tooltip content={<ChartTooltip unit={unit} decimals={decimals} />} />
                <Line
                    type="monotone"
                    dataKey="max"
                    stroke={CHART_COLORS.maximum}
                    strokeWidth={1}
                    strokeDasharray="4 3"
                    dot={false}
                    isAnimationActive={false}
                />
                <Line
                    type="monotone"
                    dataKey="min"
                    stroke={CHART_COLORS.minimum}
                    strokeWidth={1}
                    strokeDasharray="4 3"
                    dot={false}
                    isAnimationActive={false}
                />
                <Area
                    type="monotone"
                    dataKey="value"
                    stroke={CHART_COLORS.line}
                    strokeWidth={2}
                    fill={CHART_COLORS.fill}
                    fillOpacity={0.12}
                    dot={false}
                    activeDot={{ r: 3 }}
                    isAnimationActive={false}
                />
            </ComposedChart>
        </ResponsiveContainer>
    );
}
