package com.example.cantina;
import android.content.Intent;
import android.os.Bundle;
import android.widget.ArrayAdapter;
import android.widget.Spinner;
import android.widget.TextView;
import android.widget.Toast;
import org.json.JSONObject;
import org.json.JSONArray;

public class RelatorioPagamentoActivity extends BaseActivity {
    private String requestId;
    private boolean sending;
    @Override protected void onCreate(Bundle state) {
        super.onCreate(state);setContentView(R.layout.relatorio_pagamento);
        requestId=state==null?getIntent().getStringExtra("compraId"):state.getString("requestId");
        if(requestId==null)requestId=java.util.UUID.randomUUID().toString();
        ((TextView)findViewById(R.id.nomePessoa)).setText("Olá, "+Api.nome+"!");
        ((TextView)findViewById(R.id.numeroPedido)).setText("Confira os dados para enviar seu pedido à cantina.");
        ((TextView)findViewById(R.id.dataPedido)).setText("Saldo é debitado ao confirmar. Pix e cartão ficam pendentes de confirmação pela cantina.");
        ((TextView)findViewById(R.id.valorPedido)).setText("Total: "+Moeda.formatar(CarrinhoStore.totalCentavos()));
        Spinner metodo=findViewById(R.id.spinnerPagamento),recreio=findViewById(R.id.spinnerRecreio);
        metodo.setAdapter(adapter("Selecione","Pix","Cartão","Saldo"));recreio.setAdapter(adapter("09:00","15:30"));
        findViewById(R.id.btnConfirmarPagamento).setOnClickListener(v->{
            if(sending)return;
            if(CarrinhoStore.itens().isEmpty()){Toast.makeText(this,"Carrinho vazio.",Toast.LENGTH_SHORT).show();finish();return;}
            if(metodo.getSelectedItemPosition()==0){Toast.makeText(this,"Escolha a forma de pagamento.",Toast.LENGTH_SHORT).show();return;}
            JSONObject body=new JSONObject();JSONArray itens=new JSONArray();
            try {
                for(ItemCarrinho item:CarrinhoStore.itens())itens.put(new JSONObject().put("id",item.produto.id).put("quantidade",item.quantidade).put("observacao",item.observacao));
                body.put("requestId",requestId).put("metodo",metodo.getSelectedItem().toString()).put("recreio",recreio.getSelectedItem().toString())
                    .put("itens",itens).put("totalCentavos",CarrinhoStore.totalCentavos());
            }catch(Exception e){return;}
            sending=true;v.setEnabled(false);metodo.setEnabled(false);recreio.setEnabled(false);
            Api.request(this,"POST","/pedidos",body,(data,error)->{
                sending=false;v.setEnabled(true);metodo.setEnabled(true);recreio.setEnabled(true);
                if(error!=null){Toast.makeText(this,error,Toast.LENGTH_LONG).show();return;}
                CarrinhoStore.limpar();Toast.makeText(this,"Pedido "+data.optString("idPedido")+" registrado no banco.",Toast.LENGTH_LONG).show();
                startActivity(new Intent(this,ExtratoActivity.class));finish();
            });
        });
    }
    private ArrayAdapter<String> adapter(String...values){ArrayAdapter<String> a=new ArrayAdapter<>(this,android.R.layout.simple_spinner_item,values);a.setDropDownViewResource(android.R.layout.simple_spinner_dropdown_item);return a;}
    @Override protected void onSaveInstanceState(Bundle state){super.onSaveInstanceState(state);state.putString("requestId",requestId);}
}
