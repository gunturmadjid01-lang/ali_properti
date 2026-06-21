import ManagementPage from '../Components/ManagementPage';
import TableData from './TableData';
import MasterBankForm from './Form';
import requestService from './request';

export default function Index(props) {
    return <ManagementPage {...props} TableComponent={TableData} FormComponent={MasterBankForm} requestService={requestService} />;
}
