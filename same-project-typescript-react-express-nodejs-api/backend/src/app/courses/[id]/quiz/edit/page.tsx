import Site from '../../../../../components/Site';

export default async function Page({params}:{params:Promise<{id:string}>}){
 const {id}=await params;
 return <Site page="edit-quiz" id={id}/>;
}
