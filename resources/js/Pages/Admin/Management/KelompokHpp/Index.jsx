import AdminLayout from '../../../../Layouts/AdminLayout';
import ManagementPage from '../Components/ManagementPage';
import KelompokHppForm from './Form';
import requestService from './request';
import TableData from './TableData';

export default function Index(props) {
    return <ManagementPage {...props} TableComponent={TableData} FormComponent={KelompokHppForm} requestService={requestService} />;
}

Index.layout = (page) => <AdminLayout title={page?.props?.title ?? 'Management HPP'}>{page}</AdminLayout>;
