import AdminLayout from '../../../../Layouts/AdminLayout';
import ManagementPage from '../../Management/Components/ManagementPage';
import CostumerForm from './Form';
import requestService from './request';
import TableData from './TableData';

export default function Index(props) {
    return <ManagementPage {...props} separateFormPages TableComponent={TableData} FormComponent={CostumerForm} requestService={requestService} />;
}

Index.layout = (page) => <AdminLayout title={page?.props?.title ?? 'Marketing'}>{page}</AdminLayout>;
