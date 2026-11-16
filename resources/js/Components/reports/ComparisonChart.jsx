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
 * Side-by-side comparison of stations or basins. Bars are horizontal because
 * station names are long and a rotated axis is harder to read than a stacked
 * label column.
 */
export default function ComparisonChart({ items = [], unit = null, decimals = 2, labelWidth = 150, height = 300 }) {
    return (
        <ResponsiveContainer width="100%" height={height}>
            <BarChart data={items} layout="vertical" margin={{ top: 4, right: 18, bottom: 4, left: 8 }}>
                <CartesianGrid strokeDasharray="3 3" stroke={CHART_COLORS.grid} horizontal={false} />
                <XAxis
                    type="number"
                    tick={{ fontSize: 10, fill: '#64748b' }}
                    tickLine={false}
                    axisLine={false}
                    tickFormatter={(value) => Number(value).toLocaleString()}
                />
                <YAxis
                    type="category"
                    dataKey="name"
                    width={labelWidth}
                    tick={{ fontSize: 10, fill: '#334155' }}
                    tickLine={false}
                    axisLine={false}
                    interval={0}
                />
                <Tooltip content={<ChartTooltip unit={unit} decimals={decimals} />} wrapperStyle={{ outline: 'none' }} />
                <Bar dataKey="value" fill={CHART_COLORS.compare} radius={[0, 4, 4, 0]} maxBarSize={16} />
            </BarChart>
        </ResponsiveContainer>
    );
}
