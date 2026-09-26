package com.example.cantina;
import android.content.Intent;
import android.os.Bundle;
import android.widget.Button;
import android.widget.LinearLayout;
import android.widget.TextView;
import org.json.JSONArray;
import org.json.JSONObject;

public class CardapioTelaInicialActivity extends BaseActivity {
    @Override protected void onCreate(Bundle state) {
        super.onCreate(state);setContentView(R.layout.cardapio_telainicial);
        findViewById(R.id.bnt_carinho).setOnClickListener(v->startActivity(new Intent(this,CarrinhoActivity.class)));
        findViewById(R.id.bnt_extrato).setOnClickListener(v->startActivity(new Intent(this,ExtratoActivity.class)));
        findViewById(R.id.btnAtualizar).setOnClickListener(v->carregar());
        findViewById(R.id.btnSair).setOnClickListener(v->{Api.request(this,"POST","/logout",null,(data,error)->{});Api.clear();finish();});
    }
    @Override protected void onResume() {super.onResume();if(!"aluno".equals(Api.perfil)){finish();return;}carregar();}
    private void carregar() {
        TextView status=findViewById(R.id.statusCardapio);LinearLayout lista=findViewById(R.id.listaProdutos);lista.removeAllViews();
        status.setText("Carregando cardápio do banco…");findViewById(R.id.btnAtualizar).setEnabled(false);
        Api.request(this,"GET","/cardapio",null,(data,error)->{
            findViewById(R.id.btnAtualizar).setEnabled(true);if(error!=null){status.setText(error);return;}
            JSONArray products=data.optJSONArray("produtos");
            status.setText("Olá, "+Api.nome+"!"+(products==null||products.length()==0?"\nNenhum produto cadastrado.":""));
            if(products==null)return;
            for(int i=0;i<products.length();i++) {
                JSONObject p=products.optJSONObject(i);if(p==null)continue;
                Produto produto=new Produto(p.optString("id"),p.optString("nome"),p.optString("descricao")
                    +"\nSabor: "+p.optString("sabor")+"\nHorário: "+p.optString("horario"),p.optLong("precoCentavos"));
                Button button=new Button(this);button.setAllCaps(false);
                button.setText(produto.nome+"\n"+produto.precoFormatado()+" • Estoque: "+p.optInt("estoque"));button.setEnabled(p.optInt("estoque")>0);
                button.setOnClickListener(v->startActivity(new Intent(this,ProdutoActivity.class).putExtra("produto",produto)));
                lista.addView(button,new LinearLayout.LayoutParams(-1,-2));
            }
        });
    }
}
