import ChartTooltip from '@/Components/reports/ChartTooltip';
import { CHART_COLORS } from '@/Utils/reports';
import {
    Bar,
    BarChart,
    CartesianGrid,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';

/**
 * Histogram of individual readings. Values, not totals, are plotted: this answers
 * how often a depth or flow was reached, which a period summary cannot show.
 */
export default function DistributionChart({ bins = [], unit = null, decimals = 2, height = 260 }) {
    return (
        <ResponsiveContainer width="100%" height={height}>
            <BarChart data={bins} margin={{ top: 8, right: 12, bottom: 4, left: -8 }}>
                <CartesianGrid strokeDasharray="3 3" stroke={CHART_COLORS.grid} vertical={false} />
                <XAxis
                    dataKey="from"
                    tick={{ fontSize: 9, fill: '#64748b' }}
                    tickLine={false}
                    axisLine={false}
                    tickFormatter={(value) => Number(value).toLocaleString()}
                    minTickGap={12}
                />
                <YAxis
                    tick={{ fontSize: 10, fill: '#64748b' }}
                    tickLine={false}
                    axisLine={false}
                    width={48}
                    allowDecimals={false}
                />
                <Tooltip
                    content={<ChartTooltip decimals={0} />}
                    labelFormatter={(value, payload) => {
                        const bin = payload?.[0]?.payload;

                        return bin ? `${bin.from} – ${bin.to}${unit ? ` ${unit}` : ''}` : value;
                    }}
                />
                <Bar dataKey="records" fill={CHART_COLORS.distribution} radius={[3, 3, 0, 0]} maxBarSize={40} />
            </BarChart>
        </ResponsiveContainer>
    );
}
