package com.example.cantina;
import android.content.Intent;
import android.os.Bundle;
import android.widget.TextView;

public class AreaResponsavelActivity extends BaseActivity {
    @Override protected void onCreate(Bundle state) {
        super.onCreate(state);setContentView(R.layout.area_responsavel);
        findViewById(R.id.btnPedidosFilho).setOnClickListener(v->startActivity(new Intent(this,ExtratoActivity.class)));
        findViewById(R.id.btnSairResponsavel).setOnClickListener(v->{
            Api.request(this,"POST","/logout",null,(data,error)->{});Api.clear();
            startActivity(new Intent(this,MainActivity.class).addFlags(Intent.FLAG_ACTIVITY_CLEAR_TOP));finish();
        });
    }
    @Override protected void onResume() {
        super.onResume();if(!"responsavel".equals(Api.perfil)){finish();return;}
        TextView info=findViewById(R.id.infoFilho);info.setText("Carregando vínculo do responsável…");
        Api.request(this,"GET","/responsavel/filho",null,(data,error)->{
            if(error!=null){info.setText(error);return;}
            org.json.JSONObject filho=data.optJSONObject("filho");
            if(filho==null){info.setText("Vínculo não encontrado.");return;}
            info.setText("Olá, "+Api.nome+"!\n\nAluno vinculado: "+filho.optString("usuario_filho")+"\nIdade: "+filho.optInt("idade_filho"));
        });
    }
}
