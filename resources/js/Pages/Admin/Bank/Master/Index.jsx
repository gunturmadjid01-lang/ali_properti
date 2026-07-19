import { router } from '@inertiajs/react';
import AdminLayout from '../../../../Layouts/AdminLayout';
import BankPageShell from '../components/BankPageShell';

export default function Index(props) {
    return <BankPageShell {...props} description="Identitas utama bank konvensional atau syariah. Form tambah dan edit tersedia pada halaman khusus." onCreate={()=>router.visit(`${props.baseUrl}/tambah`)} onEdit={(row)=>router.visit(`${props.baseUrl}/${row.id}/edit`)} columns={[
        {key:'kode_bank',label:'Kode'},
        {key:'nama_bank',label:'Nama Bank',render:(row)=><b>{row.nama_bank}</b>},
        {key:'jenis_bank',label:'Jenis'},
        {key:'nomor_telepon',label:'Telepon'},
        {key:'email',label:'Email'},
        {key:'status',label:'Status'},
    ]}/>;
}
Index.layout=page=><AdminLayout title={page?.props?.title??'Master Bank Kredit'}>{page}</AdminLayout>;
