package com.example.cantina;
import android.os.Bundle;
import android.widget.TextView;
import org.json.JSONArray;
import org.json.JSONObject;

public class ExtratoActivity extends BaseActivity {
    @Override protected void onCreate(Bundle state) {
        super.onCreate(state);setContentView(R.layout.extrato);
        findViewById(R.id.bnt_voltar).setOnClickListener(v->finish());
        findViewById(R.id.btnAtualizarExtrato).setOnClickListener(v->carregar());carregar();
    }
    private void carregar() {
        TextView view=findViewById(R.id.produtosPedido1);view.setText("Consultando pedidos no banco…");
        Api.request(this,"GET","responsavel".equals(Api.perfil)?"/responsavel/pedidos":"/pedidos",null,(data,error)->{
            if(error!=null){view.setText(error);return;}
            StringBuilder texto=new StringBuilder();JSONArray pedidos=data.optJSONArray("pedidos");
            if(pedidos!=null)for(int i=0;i<pedidos.length();i++) {
                JSONObject p=pedidos.optJSONObject(i);if(p==null)continue;
                texto.append("Pedido ").append(p.optString("id_pedido")).append(" — ").append(p.optString("status_pedido")).append('\n');
                JSONArray itens=p.optJSONArray("itens");
                if(itens!=null)for(int j=0;j<itens.length();j++) {
                    JSONObject item=itens.optJSONObject(j);if(item==null)continue;
                    texto.append(item.optInt("quantidade")).append("x ").append(item.optString("nome")).append('\n');
                    if(!item.optString("observacao").isEmpty())texto.append("Obs.: ").append(item.optString("observacao")).append('\n');
                }
                texto.append("Total: ").append(Moeda.formatar(p.optLong("totalCentavos")))
                    .append("\nPagamento: ").append(p.optString("forma_pagamento")).append(" — ").append(p.optString("status_pagamento"))
                    .append("\nData: ").append(p.optString("dt_pedido")).append("\nRecreio: ").append(p.optString("horario_recreio")).append("\n\n");
            }
            view.setText(texto.length()==0?"Nenhum pedido registrado.":texto.toString().trim());
        });
    }
}
