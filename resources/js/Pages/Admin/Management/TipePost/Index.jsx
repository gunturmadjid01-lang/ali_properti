import AdminLayout from '../../../../Layouts/AdminLayout';
import ManagementPage from '../Components/ManagementPage';
import TipePostForm from './Form';
import requestService from './request';
import TableData from './TableData';

export default function Index(props) {
    return <ManagementPage {...props} TableComponent={TableData} FormComponent={TipePostForm} requestService={requestService} />;
}

Index.layout = (page) => <AdminLayout title={page?.props?.title ?? 'Admin'}>{page}</AdminLayout>;
