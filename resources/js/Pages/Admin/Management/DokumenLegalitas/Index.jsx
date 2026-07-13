import AdminLayout from '../../../../Layouts/AdminLayout';
import SeparatedManagementIndex from '../Components/SeparatedManagementIndex';

export default function Index(props) {
    return <SeparatedManagementIndex {...props} />;
}

Index.layout = (page) => <AdminLayout title={page?.props?.title ?? 'Admin'}>{page}</AdminLayout>;
