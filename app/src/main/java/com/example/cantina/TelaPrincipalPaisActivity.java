package com.example.cantina;

import android.content.Intent;
import android.os.Bundle;
import android.text.InputType;
import android.widget.EditText;
import android.widget.LinearLayout;
import android.widget.TextView;
import android.widget.Toast;
import androidx.appcompat.app.AlertDialog;
import org.json.JSONArray;
import org.json.JSONObject;
import java.math.BigDecimal;
import java.util.UUID;

public class TelaPrincipalPaisActivity extends BaseActivity {
    private boolean busy, carregado;
    private JSONArray filhos = new JSONArray();
    private Long diario, mensal;
    private String creditoId;
    private long creditoValor=-1;
    @Override protected void onCreate(Bundle state) {
        super.onCreate(state);
        setContentView(R.layout.tela_principalpais);
        if(state!=null) {creditoId=state.getString("creditoId");creditoValor=state.getLong("creditoValor",-1);}
        findViewById(R.id.btnTrocarAluno).setOnClickListener(v -> {
            if(busy || filhos.length()<2)return;
            String[] nomes=new String[filhos.length()];
            for(int i=0;i<filhos.length();i++)nomes[i]=filhos.optJSONObject(i).optString("usuario_filho");
            new AlertDialog.Builder(this).setTitle("Selecionar aluno").setItems(nomes,(dialog,index)->{
                Api.alunoId=filhos.optJSONObject(index).optString("cpf_filho");
                creditoId=null;creditoValor=-1;carregado=false;carregarFilho();
            }).show();
        });
        findViewById(R.id.cardExtrato).setOnClickListener(v -> abrirExtrato());
        findViewById(R.id.btnVerExtrato).setOnClickListener(v -> abrirExtrato());
        findViewById(R.id.btnMudarSaldo).setOnClickListener(v -> adicionarSaldo());
        findViewById(R.id.cardLimite).setOnClickListener(v -> editarLimites());
        findViewById(R.id.btnLimiteGasto).setOnClickListener(v -> editarLimites());
        findViewById(R.id.btnSairResponsavel).setOnClickListener(v -> {
            Api.request(this,"POST","/logout",null,(data,error)->{});Api.clear();
            startActivity(new Intent(this,TelaInicialActivity.class)
                    .addFlags(Intent.FLAG_ACTIVITY_NEW_TASK|Intent.FLAG_ACTIVITY_CLEAR_TASK));finish();
        });
    }
    private void abrirExtrato() {
        if("responsavel".equals(Api.perfil)) startActivity(new Intent(this,ExtratoActivity.class));
    }
    @Override protected void onResume() {
        super.onResume();
        if(isFinishing()||!"responsavel".equals(Api.perfil))return;
        carregarFilho();
    }
    private void carregarFilho() {
        TextView info=findViewById(R.id.infoFilho);info.setText(R.string.carregando_filho);
        Api.request(this,"GET","/responsavel/filho",null,(data,error)->{
            if(!"responsavel".equals(Api.perfil))return;
            if(error!=null){info.setText(error);return;}
            JSONArray lista=data.optJSONArray("filhos");filhos=lista==null?new JSONArray():lista;
            findViewById(R.id.btnTrocarAluno).setVisibility(filhos.length()>1?android.view.View.VISIBLE:android.view.View.GONE);
            JSONObject filho=data.optJSONObject("filho");
            if(filho==null){info.setText(R.string.vinculo_nao_encontrado);return;}
            info.setText(getString(R.string.resumo_filho,Api.nome,filho.optString("usuario_filho"),filho.optInt("idade_filho")));
        });
        carregar();
    }
    private void carregar() {
        Api.request(this,"GET","/responsavel/carteira",null,(data,error)->{
            if(!"responsavel".equals(Api.perfil))return;
            TextView saldo=findViewById(R.id.saldoAtual);
            if(error!=null){carregado=false;saldo.setText("Falha ao consultar");Toast.makeText(this,error,Toast.LENGTH_LONG).show();return;}
            carregado=true;saldo.setText(Moeda.formatar(data.optLong("saldoCentavos")));
            diario=data.isNull("limiteDiarioCentavos")?null:data.optLong("limiteDiarioCentavos");
            mensal=data.isNull("limiteMensalCentavos")?null:data.optLong("limiteMensalCentavos");
            ((TextView)findViewById(R.id.resumoLimites)).setText("Diário: "+limite(diario)+" • Mensal: "+limite(mensal));
            StringBuilder texto=new StringBuilder("Movimentações de saldo (últimas 50)\n");
            JSONArray movimentos=data.optJSONArray("movimentos");
            if(movimentos==null||movimentos.length()==0)texto.append("Nenhuma movimentação.");
            else for(int i=0;i<movimentos.length();i++) {
                JSONObject m=movimentos.optJSONObject(i);if(m==null)continue;
                texto.append(m.optString("criado_em")).append(" — ").append(m.optString("tipo"))
                    .append(": ").append(Moeda.formatar(m.optLong("valor_centavos"))).append('\n');
            }
            ((TextView)findViewById(R.id.movimentosSaldo)).setText(texto.toString());
        });
    }
    private String limite(Long value){return value==null?"Sem limite":Moeda.formatar(value);}
    private EditText campo(String hint,Long value) {
        EditText input=new EditText(this);input.setHint(hint);
        input.setInputType(InputType.TYPE_CLASS_NUMBER|InputType.TYPE_NUMBER_FLAG_DECIMAL);
        if(value!=null)input.setText(BigDecimal.valueOf(value,2).toPlainString().replace('.',','));
        return input;
    }
    private Long valor(EditText input,boolean vazio) {
        String text=input.getText().toString().trim();
        if(vazio&&text.isEmpty())return null;
        if(!text.matches("[0-9]+([,.][0-9]{1,2})?"))throw new IllegalArgumentException("Informe reais com até duas casas decimais.");
        long value=new BigDecimal(text.replace(',','.')).movePointRight(2).longValueExact();
        if(value<0||value>100000000)throw new IllegalArgumentException("O máximo é R$ 1.000.000,00.");
        return value;
    }
    private LinearLayout formulario() {
        LinearLayout form=new LinearLayout(this);form.setOrientation(LinearLayout.VERTICAL);
        int p=(int)(24*getResources().getDisplayMetrics().density);form.setPadding(p,0,p,0);return form;
    }
    private void adicionarSaldo() {
        if(busy)return;
        LinearLayout form=formulario();EditText input=campo("Valor em reais",creditoValor>0?creditoValor:null);form.addView(input);
        AlertDialog dialog=new AlertDialog.Builder(this).setTitle("Adicionar crédito local")
            .setMessage("Crédito de teste no sistema local. Não realiza cobrança bancária.")
            .setView(form).setNegativeButton("Cancelar",null).setPositiveButton("Adicionar",null).create();
        dialog.setOnShowListener(d -> dialog.getButton(AlertDialog.BUTTON_POSITIVE).setOnClickListener(v->{
            try {
                long amount=valor(input,false);if(amount==0)throw new IllegalArgumentException("Informe um valor maior que zero.");
                if(creditoId==null||creditoValor!=amount){creditoId=UUID.randomUUID().toString();creditoValor=amount;}
                JSONObject body=new JSONObject().put("valorCentavos",amount).put("requestId",creditoId);
                busy=true;dialog.dismiss();
                Api.request(this,"POST","/responsavel/carteira/creditos",body,(data,error)->{
                    busy=false;if(error!=null){Toast.makeText(this,error,Toast.LENGTH_LONG).show();return;}
                    creditoId=null;creditoValor=-1;Toast.makeText(this,"Crédito registrado.",Toast.LENGTH_SHORT).show();carregar();
                });
            } catch(Exception e){input.setError(e.getMessage()==null?"Valor inválido.":e.getMessage());}
        }));dialog.show();
    }
    private void editarLimites() {
        if(busy)return;
        if(!carregado){carregar();Toast.makeText(this,"Aguarde a consulta dos limites e tente novamente.",Toast.LENGTH_SHORT).show();return;}
        LinearLayout form=formulario();
        TextView labelD=new TextView(this);labelD.setText("Limite diário (R$)");form.addView(labelD);
        EditText dia=campo("Vazio = sem limite",diario);form.addView(dia);
        TextView labelM=new TextView(this);labelM.setText("Limite mensal (R$)");form.addView(labelM);
        EditText mes=campo("Vazio = sem limite",mensal);form.addView(mes);
        AlertDialog dialog=new AlertDialog.Builder(this).setTitle("Limites de gasto")
            .setMessage("Valem para todas as formas de pagamento. Deixe vazio para remover; zero bloqueia novas compras.")
            .setView(form).setNegativeButton("Cancelar",null).setPositiveButton("Salvar",null).create();
        dialog.setOnShowListener(d -> dialog.getButton(AlertDialog.BUTTON_POSITIVE).setOnClickListener(v->{
            Long vd,vm;
            try{vd=valor(dia,true);}catch(Exception e){dia.setError("Valor inválido; use até duas casas decimais.");return;}
            try{vm=valor(mes,true);}catch(Exception e){mes.setError("Valor inválido; use até duas casas decimais.");return;}
            try {
                JSONObject body=new JSONObject().put("limiteDiarioCentavos",vd==null?JSONObject.NULL:vd)
                    .put("limiteMensalCentavos",vm==null?JSONObject.NULL:vm);
                busy=true;dialog.dismiss();
                Api.request(this,"PUT","/responsavel/carteira/limites",body,(data,error)->{
                    busy=false;if(error!=null){Toast.makeText(this,error,Toast.LENGTH_LONG).show();return;}
                    Toast.makeText(this,"Limites salvos.",Toast.LENGTH_SHORT).show();carregar();
                });
            }catch(org.json.JSONException e){Toast.makeText(this,"Não foi possível salvar os limites.",Toast.LENGTH_SHORT).show();}
        }));dialog.show();
    }
    @Override protected void onSaveInstanceState(Bundle state) {
        super.onSaveInstanceState(state);state.putString("creditoId",creditoId);state.putLong("creditoValor",creditoValor);
    }
}
