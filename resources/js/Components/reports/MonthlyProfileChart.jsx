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
 * Month-of-year profile: the same aggregation applied per calendar month across
 * every year in range, which is how a wet season is read at a glance.
 */
export default function MonthlyProfileChart({ points = [], unit = null, decimals = 2, height = 260 }) {
    return (
        <ResponsiveContainer width="100%" height={height}>
            <BarChart data={points} margin={{ top: 8, right: 12, bottom: 0, left: -8 }}>
                <CartesianGrid strokeDasharray="3 3" stroke={CHART_COLORS.grid} vertical={false} />
                <XAxis dataKey="label" tick={{ fontSize: 10, fill: '#64748b' }} tickLine={false} axisLine={false} />
                <YAxis
                    tick={{ fontSize: 10, fill: '#64748b' }}
                    tickLine={false}
                    axisLine={false}
                    width={58}
                    tickFormatter={(value) => Number(value).toLocaleString()}
                />
                <Tooltip content={<ChartTooltip unit={unit} decimals={decimals} />} />
                <Bar dataKey="value" fill={CHART_COLORS.fill} radius={[4, 4, 0, 0]} maxBarSize={38} />
            </BarChart>
        </ResponsiveContainer>
    );
}
