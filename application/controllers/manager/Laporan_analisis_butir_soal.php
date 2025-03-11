<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Laporan_analisis_butir_soal extends Member_Controller {
	private $kode_menu = 'laporan-analisis-butir-soal';
	private $kelompok = 'laporan';
	private $url = 'manager/laporan_analisis_butir_soal';
	
    function __construct(){
		parent:: __construct();
		$this->load->model('cbt_user_model');
		$this->load->model('cbt_user_grup_model');
		$this->load->model('cbt_tes_model');
		$this->load->model('cbt_tes_token_model');
		$this->load->model('cbt_tes_topik_set_model');
		$this->load->model('cbt_tes_user_model');
		$this->load->model('cbt_tesgrup_model');
		$this->load->model('cbt_soal_model');
		$this->load->model('cbt_jawaban_model');
		$this->load->model('cbt_tes_soal_model');
		$this->load->model('cbt_tes_soal_jawaban_model');
		$this->load->model('cbt_topik_model');

        parent::cek_akses($this->kode_menu);
	}
	
    public function index(){
		$data['kode_menu'] = $this->kode_menu;
        $data['url'] = $this->url;

        $username = $this->access->get_username();
		$user_id = $this->users_model->get_login_info($username)->id;

        $query_group = $this->cbt_user_grup_model->get_group();
        $select = '';
        if($query_group->num_rows()>0){
        	$query_group = $query_group->result();
        	foreach ($query_group as $temp) {
        		$select = $select.'<option value="'.$temp->grup_id.'">'.$temp->grup_nama.'</option>';
        	}

        }else{
        	$select = '<option value="0">Tidak Ada Group</option>';
        }
        $data['select_group'] = $select;
		
		$query_tes = $this->cbt_tes_user_model->get_by_group();
        $select = '';
        if($query_tes->num_rows()>0){
        	$query_tes = $query_tes->result();
        	foreach ($query_tes as $temp) {
        		$select = $select.'<option value="'.$temp->tes_id.'">'.$temp->tes_nama.'</option>';
        	}
        }else{
			$select = '<option value="kosong">Belum Ada Tes yang Dilakukan</option>';
		}
        $data['select_tes'] = $select;
        
        $this->template->display_admin($this->kelompok.'/laporan_analisis_butir_soal_view', 'Analisis Butir Soal', $data);
    }

    public function export(){
    	$this->load->library('form_validation');
    	
    	$this->form_validation->set_rules('pilih-grup', 'Grup','required|strip_tags');
        $this->form_validation->set_rules('nama-grup', 'Grup','required|strip_tags');
        $this->form_validation->set_rules('pilih-tes', 'Nama Tes','required|strip_tags');
		$this->form_validation->set_rules('nama-tes', 'Nama Tes','required|strip_tags');
		
        if($this->form_validation->run() == TRUE){
            $tes = $this->input->post('pilih-tes', true);
            $grup = $this->input->post('pilih-grup', true);
            $nama_grup = $this->input->post('nama-grup', true);
        	$nama_tes = $this->input->post('nama-tes', true);
        
            // Mengambil Data Peserta berdasarkan grup dan tes
            $query_tes = $this->cbt_tesgrup_model->get_by_tes_id_and_grup($tes , $grup);
            
            if($query_tes->num_rows() > 0){
                $topik_id = $query_tes->result_array()[0]["tset_topik_id"];
                $grup_nama = $query_tes->result_array()[0]["grup_nama"];
                $query_soal = $this->cbt_soal_model->get_by_kolom('soal_topik_id', $topik_id);
                
                $query_user = $this->db->select("user_id")
                                        ->where("user_grup_id" , $grup)
                                        ->from("cbt_user")->get();
                                        
                $all_user = [];
                
                $data_soal_benar = [];
                
                
                
                foreach($query_user->result_array() as $user){
                    $tes_user = $this->db->select("*")
                                        ->where("tesuser_user_id" , $user["user_id"])
                                        ->where("tesuser_tes_id" , $tes)
                                        ->from("cbt_tes_user")->get();
                                        
                    
                    if($tes_user->num_rows() > 0){
                        $tes_user = $tes_user->result_array()[0];
                        $tes_soal = $this->db->select("tessoal_id")
                                        ->where("tessoal_tesuser_id", $tes_user["tesuser_id"])
                                        ->from("cbt_tes_soal")->get();
                        
                        $all_jawaban_test = [];
                        
                        foreach($tes_soal->result_array() as $soal){
                            $jawaban_soal = $this->db->select("soaljawaban_jawaban_id")
                                            ->where("soaljawaban_tessoal_id", $soal["tessoal_id"])
                                            ->where("soaljawaban_selected" , 1)
                                            ->from("cbt_tes_soal_jawaban")->get();
                            if($jawaban_soal->num_rows() > 0){
                                $jawaban_soal = $jawaban_soal->result_array()[0];
                                $jawaban_detail = $this->db->select("jawaban_benar")
                                                ->where("jawaban_id", $jawaban_soal["soaljawaban_jawaban_id"])
                                                ->from("cbt_jawaban")->get();
                                
                                $all_jawaban_test[] = $soal + ["jawaban_soal" => $jawaban_soal + [ "cek_jawaban" => $jawaban_detail->result_array()]];
                            }
                        }
                        
                        $all_user[] = $user + ["tes_user" => $tes_user , "tes_soal" =>  $all_jawaban_test];
                    }else{
                        $all_user[] = $user;
                    }
                }
                
                $count_jawaban = [];
                
                $total_mengerjakan = [];
                
                foreach($all_user as $user){
                    if(!empty($user["tes_user"])){
                        foreach($user["tes_soal"] as $user_soal){
                            $count_jawaban[] = $user_soal["jawaban_soal"]["soaljawaban_jawaban_id"];
                        }
                        
                        $total_mengerjakan[] = $user;
                    }
                }
                
                
                $count_jawaban = array_count_values($count_jawaban);
                
                $query_soal = $query_soal->result();

				$query_topik = $this->cbt_topik_model->get_by_kolom_limit('topik_id', $topik_id, 1)->row();

				$soal_table = '
				    <style>
				        *{
				            font-family : "Arial"
				        }
				        
				        .temp-benar{
				            width: 10%
				        }
				        
				        .temp-jawaban{
				            width: 75%
				        }
				        
				        @media print{
			            .temp-benar{
			                width: 15%
				        }
				        
				        .temp-jawaban{
				            width: 70%
				        }
				        }
				    </style>
				    <div style="display:flex;gap:10px">
				        <div>
    				        <H3>Soal</h3>
        					<b>Topik = '.$query_topik->topik_nama.'</b>
				        </div>
				        <div>
				            <H3>Grup</h3>
        					<b>Kelas = '.$grup_nama.'</b>
				        </div>
				    </div>
				    <hr />
    				<table class="table" border="0">
					';

				$a = 1;

				foreach($query_soal as $temp){
					$posisi = $this->config->item('upload_path').'/topik_'.$temp->soal_topik_id;

					$soal = $temp->soal_detail;
					$soal = str_replace("[base_url]", base_url(), $soal);

					if($temp->soal_tipe==1){
						$tipe_soal = 'Pilihan Ganda';
					}else if($temp->soal_tipe==2){
						$tipe_soal = 'Essay';
					}

					if(!empty($temp->soal_audio)){
						$posisi = $this->config->item('upload_path').'/topik_'.$temp->soal_topik_id;
						$soal = $soal.'<br />
							<audio controls>
							<source src="'.base_url().$posisi.'/'.$temp->soal_audio.'" type="audio/mpeg">
							Your browser does not support the audio element.
							</audio>
						';
					}

					$soal_table = $soal_table.'
							<tr>
							<td>'.$a++.'</td>
							<td colspan="2">'.$soal.'</td>
							<td width="15%"></td>
							</tr>
					';

					$query_jawaban = $this->cbt_jawaban_model->get_by_soal($temp->soal_id);
					if($query_jawaban->num_rows()>0){
						$query_jawaban = $query_jawaban->result();
						
						
						foreach ($query_jawaban as $jawaban) {
							$temp_jawaban = $jawaban->jawaban_detail;
							$temp_jawaban = str_replace("[base_url]", base_url(), $temp_jawaban);
                            $jumlah = 0;
                            if(empty($count_jawaban[$jawaban->jawaban_id])){
                                $jumlah = 0;
                            }else{
                                $jumlah = $count_jawaban[$jawaban->jawaban_id];
                            }
                            
                            $jumlah_siswa = count($total_mengerjakan);
                            
                            
							$temp_benar = '';
							if($jawaban->jawaban_benar==1){
								$temp_benar = '
								    <div style="display:flex;align-items:center;gap:10px;justify-content:space-beetween">
								        <p style="color:green">'. $jumlah .'/ '. $jumlah_siswa .'</p>
								        <div style="width:20px;height:20px;border-radius:50%;background-color:green"></div>
								    </div>
								';
							}elseif($jawaban->jawaban_benar==0){
								$temp_benar = '
								     <div style="display:flex;align-items:center;gap:10px;justify-content:space-beetween">
								        <p style="color:red">'. $jumlah .'/ '. $jumlah_siswa .'</p>
								        <div style="width:20px;height:20px;border-radius:50%;border:1px solid red"></div>
								    </div>
								';
							}

							$soal_table = $soal_table.'
								<tr>
									<td width="5%"> </td>
									<td  class="temp-benar">'.$temp_benar.'</td>
									<td  class="temp-jawaban">'.$temp_jawaban.'</td>
									<td width="10%"></td>
								</tr>
							';
						}
					}

					$soal_table = $soal_table.'
								<tr>
									<td colspan="4"> ---------------------------------------------------------------------------- </td>
								</tr>
							';
				}

				$soal_table = $soal_table. '
				    <script>
				        window.print()
				    </script>
				';
                
				echo $soal_table;
            }else{
                echo "Data tidak ditemukan" .  "<a href='https://cbtguruhebat.my.id/index.php/manager/laporan_analisis_butir_soal'>Kembali</a>";
            }
            
        }
    }
    
}