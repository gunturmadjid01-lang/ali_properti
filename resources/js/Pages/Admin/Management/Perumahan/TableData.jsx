import { Link } from '@inertiajs/react';
import { Calculator, Eye } from 'lucide-react';
import { Button } from '../../../../Components/UI';
import { useResourcePermissions } from '../../../../Utils/permissions';
import ManagementTableAccordion from '../Components/ManagementTableAccordion';

export default function TableData(props) {
    const rabPermissions = useResourcePermissions('rab-perumahan', props.baseUrl);
    const canOpenRab = rabPermissions.canView || rabPermissions.canCreate || rabPermissions.canManage;

    return (
        <ManagementTableAccordion
            {...props}
            showDetailAction={false}
            extraActions={(row) => (
                <>
                    {canOpenRab && (
                        <Button as={Link} variant="outline" size="sm" title="RAB Perumahan" href={row.hpp_url}>
                            <Calculator size={15} /> HPP
                        </Button>
                    )}
                    <Button as={Link} variant="outline" size="sm" title="Lihat Detail" href={row.detail_url}>
                        <Eye size={15} />
                    </Button>
                </>
            )}
        />
    );
}
