import { Link } from '@inertiajs/react';
import { Calculator, Eye } from 'lucide-react';
import { Button } from '../../../../Components/UI';
import ManagementTableAccordion from '../Components/ManagementTableAccordion';

export default function TableData(props) {
    return (
        <ManagementTableAccordion
            {...props}
            extraActions={(row) => (
                <>
                    <Button as={Link} variant="outline" size="sm" title="HPP Perumahan" href={row.hpp_url}>
                        <Calculator size={15} />
                    </Button>
                    <Button as={Link} variant="outline" size="sm" title="Lihat Detail" href={row.detail_url}>
                        <Eye size={15} />
                    </Button>
                </>
            )}
        />
    );
}
