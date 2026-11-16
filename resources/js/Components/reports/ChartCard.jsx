import { Card, CardBody, CardHeader } from '@/Components/Card';

/**
 * Frame shared by every chart: heading, optional action, and an explicit
 * message when there is nothing to plot. An empty series is a real answer about
 * the data, so it is stated rather than drawn as a flat line at zero.
 */
export default function ChartCard({ icon, title, description, action, empty, emptyMessage, children, bodyClassName }) {
    return (
        <Card>
            <CardHeader icon={icon} title={title} description={description} action={action} />
            <CardBody className={bodyClassName}>
                {empty ? (
                    <p className="py-10 text-center text-[12px] text-muted">{emptyMessage ?? 'No approved observations match these filters.'}</p>
                ) : (
                    children
                )}
            </CardBody>
        </Card>
    );
}
