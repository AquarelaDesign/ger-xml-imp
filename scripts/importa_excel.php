<?php
   use PhpOffice\PhpSpreadsheet\IOFactory;
   
   error_reporting(E_ERROR);
   ini_set('display_errors', 'On');

   header("Access-Control-Allow-Origin: *");
   header('Access-Control-Allow-Methods: GET, POST, PATCH, PUT, DELETE, OPTIONS');
   header('Access-Control-Allow-Headers: Origin, Content-Type, X-Auth-Token, Process-Data'); 

   require_once __DIR__ . '/vendor/autoload.php';

   $arqXML = '';
   $arqXLS = '';
   
   if (!empty($_FILES['file'])) {
      foreach ($_FILES['file']['name'] as $key=>$val) {
         $file_name = $_FILES['file']['name'][$key];

         
         $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

         if ($ext != "xml" && $ext != "xls") {
            $ret['error'] = "Somente arquivos no formato 'XML' ou 'XLS' podem ser enviados.";
            echo json_encode($ret);
            die;
         }
         
         $filenamewithoutextension = pathinfo($file_name, PATHINFO_FILENAME);

         if (!file_exists(getcwd(). '/uploads')) {
            mkdir(getcwd(). '/uploads', 777);
         }

         $filename_to_store = str_replace(' ', '_', $filenamewithoutextension). '.' . $ext;
         move_uploaded_file($_FILES['file']['tmp_name'][$key], getcwd(). '/uploads/'.$filename_to_store);
         
         if ($ext == "xml" && $arqXML == '') {
            $arqXML = $filename_to_store;
         }
         
         if ($ext == "xls" && $arqXLS == '') {
            $arqXLS = $filename_to_store;
         }
         
      }
      
      if (empty($arqXML) || empty($arqXLS)) {
         
         unlink(getcwd(). '/uploads/' . $arqXML);
         unlink(getcwd(). '/uploads/' . $arqXLS);

         $ret['error'] = "Os arquivo 'XLS' e 'XML' deve ser enviados juntos."
                       . PHP_EOL . $arqXLS . PHP_EOL . $arqXML;
         echo json_encode($ret);
         die;
      } else {
         $arqXML = getcwd() . '/uploads/' . $arqXML;
         $arqXLS = getcwd() . '/uploads/' . $arqXLS;
      }
   }

   if (empty($arqXML) || empty($arqXLS)) {
      $ret['error'] = "Os arquivo 'XLS' e 'XML' não puderam ser carregador.";
      echo json_encode($ret);
      die;
   }
   
   // Lê arquivo XLS (Planilha)
   $planilha  = IOFactory::load($arqXLS);
   $planDados = $planilha->getActiveSheet()->toArray(null, true, true, true);
   //echo print_r($planDados, TRUE);

   // Lê arquivo XML (declaracaoImportacao)
   $doc = new DOMDocument();
   $doc->load($arqXML);    // Carrega do arquivo xml
   $doc->encoding = 'utf-8';

   unlink(getcwd(). '/uploads/' . $arqXML);
   unlink(getcwd(). '/uploads/' . $arqXLS);
   
   $tmpAdicao = [];
   $Remetente = [];
   $Emitente  = [];
   
   $Emitente['cnpj']    = $doc->getElementsByTagName('importadorNumero')->item(0)->nodeValue;
   $Emitente['xnome']   = $doc->getElementsByTagName('importadorNome')->item(0)->nodeValue;
   $Emitente['xlgr']    = $doc->getElementsByTagName('importadorEnderecoLogradouro')->item(0)->nodeValue;
   $Emitente['nro']     = $doc->getElementsByTagName('importadorEnderecoNumero')->item(0)->nodeValue;
   $Emitente['xcpl']    = $doc->getElementsByTagName('importadorEnderecoComplemento')->item(0)->nodeValue;
   $Emitente['xbairro'] = $doc->getElementsByTagName('importadorEnderecoBairro')->item(0)->nodeValue;
   $Emitente['xmun']    = $doc->getElementsByTagName('importadorEnderecoMunicipio')->item(0)->nodeValue;
   $Emitente['uf']      = $doc->getElementsByTagName('importadorEnderecoUf')->item(0)->nodeValue;
   $Emitente['cep']     = $doc->getElementsByTagName('importadorEnderecoCep')->item(0)->nodeValue;
   $Emitente['cpais']   = '1058';
   $Emitente['xpais']   = 'BRASIL';
   $Emitente['fone']    = $doc->getElementsByTagName('importadorNumeroTelefone')->item(0)->nodeValue;
   $Emitente['crt']     = '3';
   $Emitente['email']   = '';

   if ($Emitente['xmun'] == 'CURITIBA') {
      $Emitente['cmun'] = '4106902';
   } elseif ($Emitente['xmun'] == 'SAO JOSE DOS PINHAIS') {
      $Emitente['cmun'] = '4125506';
   } else {
      $Emitente['cmun'] = '';
   }

   if ($Emitente['cnpj'] == '07844179000102') {
      $Emitente['xfant'] = 'MIRANDA DIESEL';
      $Emitente['ie']    = '9058484889';
   } else {
      $Emitente['xfant'] = '';
      $Emitente['ie']    = '';
   }

   $pro_cExportador  = $doc->getElementsByTagName('importadorEnderecoUf')->item(0)->nodeValue;
   $pro_UFDesemb     = $doc->getElementsByTagName('importadorEnderecoUf')->item(0)->nodeValue;
   $pro_xLocDesemb   = $doc->getElementsByTagName('armazenamentoRecintoAduaneiroNome')->item(0)->nodeValue;
   $pro_dDI          = $doc->getElementsByTagName('dataRegistro')->item(0)->nodeValue;
   $pro_dDesemb      = $doc->getElementsByTagName('dataDesembaraco')->item(0)->nodeValue;

   $PesoBruto        = floatval($doc->getElementsByTagName('cargaPesoBruto')->item(0)->nodeValue) / 100000;
   $embalagem        = $doc->getElementsByTagName('embalagem')->item(0);
   $nomeEmbalagem    = $embalagem->getElementsByTagName('nomeEmbalagem')->item(0)->nodeValue;
   $quantidadeVolume = $embalagem->getElementsByTagName('quantidadeVolume')->item(0)->nodeValue;
      
   $c   = 0;
   $Rem = 0;
   $adicao = $doc->getElementsByTagName('adicao');
   foreach ($adicao as $add) {
      $cont_add = intval($add->getElementsByTagName('numeroAdicao')->item(0)->nodeValue);
      
      if ($Rem == 0) {
         $Remetente['cnpj']    = '';
         $Remetente['xnome']   = $add->getElementsByTagName('fornecedorNome')->item(0)->nodeValue;
         $Remetente['xfant']   = '';
         $Remetente['xlgr']    = $add->getElementsByTagName('fornecedorLogradouro')->item(0)->nodeValue;
         $Remetente['nro']     = $add->getElementsByTagName('fornecedorNumero')->item(0)->nodeValue;
         $Remetente['xcpl']    = '';
         $Remetente['xbairro'] = '';
         $Remetente['cmun']    = '';
         $Remetente['xmun']    = $add->getElementsByTagName('fornecedorCidade')->item(0)->nodeValue;
         $Remetente['cpais']   = $add->getElementsByTagName('paisOrigemMercadoriaCodigo')->item(0)->nodeValue;
         $Remetente['xpais']   = $add->getElementsByTagName('paisOrigemMercadoriaNome')->item(0)->nodeValue;
         $Remetente['ie']      = '';
         $Remetente['uf']      = 'EX';
         $Remetente['cep']     = '99999999';
         $Remetente['fone']    = '';
         $Remetente['email']   = '';
         
         $Rem = 1;
      }
      $c++;
      
      $tmpAdicao[$cont_add]['pro_nDI']         = $add->getElementsByTagName('numeroDI')->item(0)->nodeValue;
      $tmpAdicao[$cont_add]['pro_tpViaTransp'] = $add->getElementsByTagName('dadosCargaViaTransporteCodigo')->item(0)->nodeValue;
      $tmpAdicao[$cont_add]['pro_nAdicao']     = $add->getElementsByTagName('numeroAdicao')->item(0)->nodeValue;
      
      $m = 1;
      $itens = $add->getElementsByTagName('mercadoria');
      foreach ($itens as $item) {
         foreach ($item->childNodes as $merc) {
         
            switch ($merc->nodeName) {   
               case 'numeroSequencialItem': 
                  $tmpAdicao[$cont_add][$m]['item'] = intval($merc->nodeValue); 
                  break;   
               case 'descricaoMercadoria': 
                  $mercad = str_replace(' - 057','-057',$merc->nodeValue);
                  $tmp_arr = explode(' -', $mercad);
                  $tmpAdicao[$cont_add][$m]['descricao'] = !empty(trim($tmp_arr[0])) ? trim($tmp_arr[0]) : trim($tmp_arr[1]); 
                  $tmpAdicao[$cont_add][$m]['descricaoCompleta'] = !empty(trim($tmp_arr[0])) ? trim($tmp_arr[1]) : trim($tmp_arr[2]); 
                  break;   
               case 'unidadeMedida': 
                  $tmpAdicao[$cont_add][$m]['unidade'] = trim($merc->nodeValue); 
                  break;   
            }
         }
         $m++;
      } 
   }
   
   $i = 0;
   $Mercadorias = [];
   for ($row = 7; $row <= count($planDados); $row ++) {
      if ($planDados[$row] !== null) {
         $adi = 0;
         foreach($planDados[$row] as $key => $value) {
            switch ($key) {   
               case 'C':  // ADIÇÃO 
                  $adi = intval($value);
                  break;   
               case 'E':  // NCM 
                  $Mercadorias[$i]['pro_NCM'] = trim($value);  
                  break;   
               case 'F':  // REFERENCIA 
                  $Mercadorias[$i]['pro_xProd'] = trim($value);  
                  break;   
               case 'H':  // PESO TOTAL 
                  $Mercadorias[$i]['PesoLiquido'] = number_format(floatval($value),5,'.','');  
                  break;   
               case 'I':  // QTDE 
                  $Mercadorias[$i]['pro_qCom'] = number_format(floatval($value),0,'.','');  
                  break;   
               case 'J':  // VALOR UNITARIO 
                  $Mercadorias[$i]['pro_vUnCom'] = number_format(floatval($value),5,'.','');  
                  break;   
               case 'K':  // FOB
                  $Mercadorias[$i]['FOB'] = number_format(floatval($value),5,'.','');  
                  break;   
               case 'L':  // FRETE
                  $Mercadorias[$i]['pro_vFrete'] = number_format(floatval($value),5,'.','');  
                  break;   
               case 'M':  // SEGURO
                  $Mercadorias[$i]['pro_vSeg'] = number_format(floatval($value),5,'.','');  
                  break;   
               case 'N':  // THC
                  $Mercadorias[$i]['THC'] = number_format(floatval($value),5,'.','');  
                  break;   
               case 'O':  // CIF+THC 
                  $Mercadorias[$i]['pro_vProd'] = number_format(floatval($value),5,'.','');  
                  break;   
               case 'P':  // % II
                  $Mercadorias[$i]['IIPer'] = number_format(floatval($value),5,'.','');  
                  break;   
               case 'Q':  // II
                  $Mercadorias[$i]['pro_vII'] = number_format(floatval($value),5,'.','');  
                  break;   
               case 'R':  // VALOR NF
                  $Mercadorias[$i]['pro_IPIBas'] = number_format(floatval($value),5,'.','');  
                  break;   
               case 'S':  // % IPI
                  $Mercadorias[$i]['IPIPer'] = number_format(floatval($value),5,'.','');  
                  break;   
               case 'T':  // IPI 
                  $Mercadorias[$i]['pro_vIPI'] = number_format(floatval($value),5,'.','');  
                  break;   
               case 'U':  // TX.SISC. 
                  $Mercadorias[$i]['pro_TXSISC'] = number_format(floatval($value),5,'.','');  
                  break;   
               case 'V':  // % PIS
                  $Mercadorias[$i]['PISPer'] = number_format(floatval($value),5,'.','');  
                  break;   
               case 'W':  // PIS
                  $Mercadorias[$i]['pro_vPIS'] = number_format(floatval($value),5,'.','');  
                  break;   
               case 'X':  // % COFINS
                  $Mercadorias[$i]['COFINSPer'] = number_format(floatval($value),5,'.','');  
                  break;   
               case 'Y':  // COFINS
                  $Mercadorias[$i]['pro_vCOFINS'] = number_format(floatval($value),5,'.','');  
                  break;   
               case 'Z':  // OUTRAS
                  $Mercadorias[$i]['OUTRAS'] = number_format(floatval($value),5,'.','');  
                  break;   
               case 'AA': // AFRMM
                  $Mercadorias[$i]['pro_AFRMM'] = number_format(floatval($value),5,'.','');  
                  break;   
               case 'AD': // % BASE
                  $Mercadorias[$i]['BasePer'] = number_format(floatval($value),5,'.','');  
                  break;   
               case 'AE': // BASE ICMS 
                  $Mercadorias[$i]['ICMSBas'] = number_format(floatval($value),5,'.','');  
                  break;   
               case 'AF': // % ICMS 
                  $Mercadorias[$i]['ICMSPer'] = number_format(floatval($value),5,'.','');  
                  break;   
               case 'AG': // ICMS 
                  $Mercadorias[$i]['ICMSVal'] = number_format(floatval($value),5,'.','');  
                  break;   
               case 'AH': // CUSTO UNITARIO
                  $Mercadorias[$i]['CustoUN'] = number_format(floatval($value),5,'.','');  
                  break;   
               case 'AI': // CUSTO TOTAL
                  $Mercadorias[$i]['CustoTotal'] = number_format(floatval($value),5,'.','');  
                  break;   
            }
         }
         
         $Mercadorias[$i]['pro_nitem'] = $i + 1;
         $Mercadorias[$i]['pro_CFOP'] = '3102';
         $Mercadorias[$i]['ICMSRec'] = 12;
         $Mercadorias[$i]['pro_Cean'] = 'SEM GTIN';
         $Mercadorias[$i]['pro_vOutro'] = $Mercadorias[$i]['pro_TXSISC'] + $Mercadorias[$i]['pro_AFRMM'];
         
         $flag = 0;
         for ($x = 1; $x <= count($tmpAdicao[$adi]); $x ++) {
            
            $mercad = str_replace(' - ', '-', trim($Mercadorias[$i]['pro_xProd']));
            if (trim($tmpAdicao[$adi][$x]['descricao']) == $mercad) {
               $Mercadorias[$i]['pro_uCom'] = $tmpAdicao[$adi][$x]['unidade'] == 'KIT' ? 'JG' : 'PC';
               
               $tmp_cod = explode(' ', $tmpAdicao[$adi][$x]['descricao']);
               $Mercadorias[$i]['pro_cProd'] = str_replace('-', '', $tmp_cod[0]);
               $Mercadorias[$i]['pro_xProd'] = $tmpAdicao[$adi][$x]['descricaoCompleta'];

               $Mercadorias[$i]['pro_nSeqAdic'] = $tmpAdicao[$adi][$x]['item'];
               $Mercadorias[$i]['pro_nDI'] = $tmpAdicao[$adi]['pro_nDI'];
               $Mercadorias[$i]['pro_tpViaTransp'] = $tmpAdicao[$adi]['pro_tpViaTransp'];
               $Mercadorias[$i]['pro_nAdicao'] = $tmpAdicao[$adi]['pro_nAdicao'];
               
               $flag = 1;
               break;
            }
            if ($flag == 1) break;
         }
         
         if ($flag == 0) {
            $Mercadorias[$i]['pro_uCom'] = 'PC';
         }
         
         $i++;
      }
   }
   
   for ($x = 0; $x < count($Mercadorias); $x ++) {
      // CIF
      //$Mercadorias[$x]['pro_vProd']   =  $Mercadorias[$x]['FOB'] 
      //                                +  $Mercadorias[$x]['pro_vFrete']
      //                                +  $Mercadorias[$x]['pro_vSeg']
      //                                +  $Mercadorias[$x]['THC'];
      
      //$Mercadorias[$x]['pro_vUnCom']  =  $Mercadorias[$x]['pro_vProd'] / $Mercadorias[$x]['pro_qCom'];
      //$Mercadorias[$x]['pro_vII']     = ($Mercadorias[$x]['pro_vProd']  * $Mercadorias[$x]['IIPer']) / 100;
      
      //$Mercadorias[$x]['pro_IPIBas']  =  $Mercadorias[$x]['pro_vProd']  + $Mercadorias[$x]['pro_vII'];
      //$Mercadorias[$x]['pro_vIPI']    = ($Mercadorias[$x]['pro_IPIBas'] * $Mercadorias[$x]['IPIPer']) / 100;
      //$Mercadorias[$x]['pro_vPIS']    = ($Mercadorias[$x]['pro_vProd']  * $Mercadorias[$x]['PISPer']) / 100;
      //$Mercadorias[$x]['pro_vCOFINS'] = ($Mercadorias[$x]['pro_vProd']  * $Mercadorias[$x]['COFINSPer']) / 100;
      
      $Mercadorias[$x]['pro_ICMSBas'] = ($Mercadorias[$x]['pro_vProd'] 
                                      +  $Mercadorias[$x]['pro_vII']
                                      +  $Mercadorias[$x]['pro_vIPI']
                                      +  $Mercadorias[$x]['pro_vPIS']
                                      +  $Mercadorias[$x]['pro_vCOFINS']
                                      +  $Mercadorias[$x]['pro_TXSISC']
                                      +  $Mercadorias[$x]['pro_AFRMM'])
                                      /  (1 - ($Mercadorias[$x]['BasePer'] / 100));
      $Mercadorias[$x]['pro_vICMS']   = ($Mercadorias[$x]['pro_ICMSBas'] * $Mercadorias[$x]['ICMSPer']) / 100;
      
      
      $Mercadorias[$x]['pro_vProd']   = number_format(floatval($Mercadorias[$x]['pro_vProd']),5,'.','');
      $Mercadorias[$x]['pro_vII']     = number_format(floatval($Mercadorias[$x]['pro_vII']),5,'.','');
      $Mercadorias[$x]['pro_vIPI']    = number_format(floatval($Mercadorias[$x]['pro_vIPI']),5,'.','');
      $Mercadorias[$x]['pro_vPIS']    = number_format(floatval($Mercadorias[$x]['pro_vPIS']),5,'.','');
      $Mercadorias[$x]['pro_vCOFINS'] = number_format(floatval($Mercadorias[$x]['pro_vCOFINS']),5,'.','');
      $Mercadorias[$x]['pro_IPIBas']  = number_format(floatval($Mercadorias[$x]['pro_IPIBas']),5,'.','');
      $Mercadorias[$x]['pro_ICMSBas'] = number_format(floatval($Mercadorias[$x]['pro_ICMSBas']),5,'.','');
      $Mercadorias[$x]['pro_vICMS']   = number_format(floatval($Mercadorias[$x]['pro_vICMS']),5,'.','');
      $Mercadorias[$x]['pro_vUnCom']  = number_format(floatval($Mercadorias[$x]['pro_vUnCom']),5,'.','');
      $Mercadorias[$x]['pro_vOutro']  = number_format(floatval($Mercadorias[$x]['pro_vOutro']),5,'.','');
   }
   
   for ($x = 0; $x < count($Mercadorias); $x ++) {
      $it           = $x + 1;
      $vBC         += $Mercadorias[$x]['pro_ICMSBas'];
      $vICMS       += $Mercadorias[$x]['pro_vICMS'];
      $vProd       += $Mercadorias[$x]['pro_vProd'];
      $vFrete      += $Mercadorias[$x]['pro_vFrete'];
      $vSeg        += $Mercadorias[$x]['pro_vSeg'];
      $vIPI        += $Mercadorias[$x]['pro_vIPI'];
      $vNF         += $Mercadorias[$x]['CustoTotal'];
      $vTXSISC     += $Mercadorias[$x]['pro_TXSISC'];
      $vPIS        += $Mercadorias[$x]['pro_vPIS'];
      $vCOFINS     += $Mercadorias[$x]['pro_vCOFINS'];
      $PesoLiquido += $Mercadorias[$x]['PesoLiquido'];
      $vAFRMM      += $Mercadorias[$x]['pro_AFRMM'];
      $vFOB        += $Mercadorias[$x]['FOB'];
      $vII         += $Mercadorias[$x]['pro_vII'];
   }

   /*
   $Totais['Frete_por_Conta']  = 1;
   $Totais['vFrete']  = number_format(floatval($planDados[4]['L']), 4,'.','');
   $Totais['vSeg']    = number_format(floatval($planDados[4]['M']), 4,'.','');
   $Totais['vProd']   = number_format(floatval($planDados[4]['O']), 4,'.','');
   $Totais['vII']     = number_format(floatval($planDados[4]['Q']), 4,'.','');
   $Totais['vIPI']    = number_format(floatval($planDados[4]['T']), 4,'.','');
   $Totais['vTXSISC'] = number_format(floatval($planDados[4]['U']), 4,'.','');
   $Totais['vPIS']    = number_format(floatval($planDados[4]['W']), 4,'.','');
   $Totais['vCOFINS'] = number_format(floatval($planDados[4]['Y']), 4,'.','');
   $Totais['vAFRMM']  = number_format(floatval($planDados[4]['AA']),4,'.','');
   $Totais['vICMS']   = number_format(floatval($planDados[4]['AG']),4,'.','');
   $Totais['vNF']     = number_format(floatval($planDados[4]['AI']),4,'.','');
   */
   
   $Totais['vFrete']  = number_format(floatval($vFrete), 4,'.','');
   $Totais['vSeg']    = number_format(floatval($vSeg), 4,'.','');
   $Totais['vProd']   = number_format(floatval($vProd), 4,'.','');
   $Totais['vII']     = number_format(floatval($vII), 4,'.','');
   $Totais['vIPI']    = number_format(floatval($vIPI), 4,'.','');
   $Totais['vTXSISC'] = number_format(floatval($vTXSISC), 4,'.','');
   $Totais['vPIS']    = number_format(floatval($vPIS), 4,'.','');
   $Totais['vCOFINS'] = number_format(floatval($vCOFINS), 4,'.','');
   $Totais['vAFRMM']  = number_format(floatval($vAFRMM), 4,'.','');
   $Totais['vICMS']   = number_format(floatval($vICMS), 4,'.','');
   $Totais['vNF']     = number_format(floatval($vNF), 4,'.','');
   $Totais['vBC']     = number_format(floatval($vBC), 4,'.','');
   $Totais['vOutro']  = number_format(floatval($vTXSISC + $vAFRMM), 4,'.','');
   
   $DadosAdicionais['pro_cExportador']  = $pro_cExportador;
   $DadosAdicionais['pro_UFDesemb']     = $pro_UFDesemb;
   $DadosAdicionais['pro_xLocDesemb']   = $pro_xLocDesemb;
   $DadosAdicionais['pro_dDI']          = $pro_dDI;
   $DadosAdicionais['pro_dDesemb']      = $pro_dDesemb;

   $DadosAdicionais['Embalagem']        = $nomeEmbalagem;
   $DadosAdicionais['Volume']           = number_format(floatval($quantidadeVolume),4,'.','');
   $DadosAdicionais['PesoBruto']        = number_format(floatval($PesoBruto),3,'.','');
   $DadosAdicionais['PesoLiquido']      = number_format(floatval($planDados[4]['H']),3,'.','');
   //$DadosAdicionais['PesoLiquido']      = number_format(floatval($DadosAdicionais['PesoLiquido']),3,'.','');
   
   $DadosAdicionais['Referencia']       = $planDados[3]['B'];
   $DadosAdicionais['Processo']         = $planDados[4]['B'];
   $DadosAdicionais['NRDI']             = $planDados[4]['D'];
   $DadosAdicionais['Dados_Adicionais'] = "PROCESSO: " . $DadosAdicionais['Processo'] . " | "
                                        . "REFERÊNCIA: " . $DadosAdicionais['Referencia'] . " | "
                                        . "NR DI: " . $DadosAdicionais['NRDI'] . " | "
                                        . "VALOR DO PIS: R$ " . formataMoeda($Totais['vPIS']) . " | "
                                        . "COFINS: R$ " . formataMoeda($Totais['vCOFINS']) . " | "
                                        . "TAXA SISCOMEX: R$ " . formataMoeda($Totais['vTXSISC']) . " | "
                                        . "II: R$ " . formataMoeda($Totais['vII']);
   
   //foreach($Totais as $campo => $valor) {
   //   $Totais[$campo] = number_format(floatval($valor),5,'.','');
   //}

   $data['Emitente']  = $Emitente;
   $data['Remetente'] = $Remetente;
   $data['DadosNF']   = $Mercadorias;
   $data['Totais']    = $Totais;
   $data['DadosAdicionais'] = $DadosAdicionais;
   echo json_encode($data);
   //die;

function formataMoeda($valor, $dec=2,$sMil='.',$sDec=',') {
   return number_format($valor,$dec,$sDec,$sMil);
}