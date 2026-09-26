package com.example.cantina;

import androidx.appcompat.app.AppCompatActivity;
import androidx.core.view.ViewCompat;
import androidx.core.view.WindowInsetsCompat;
import androidx.core.graphics.Insets;
import android.view.View;

public abstract class BaseActivity extends AppCompatActivity {
    private static int visibleScreens;
    @Override protected void onStart() {
        super.onStart();
        visibleScreens++;
    }
    @Override protected void onStop() {
        super.onStop();
        visibleScreens--;
        // Switching screens keeps at least one started Activity; backgrounding locks the parents' area.
        if (visibleScreens == 0 && !isChangingConfigurations() && "responsavel".equals(Api.perfil)) {
            Api.request(this,"POST","/logout",null,(data,error)->{});
            Api.clear();
        }
    }
    @Override protected void onResume() {
        super.onResume();
        if (!(this instanceof TelaInicialActivity) && !(this instanceof MainActivity) && !(this instanceof CadastroPaisActivity)) {
            boolean parent = this instanceof AreaResponsavelActivity || this instanceof TelaPrincipalPaisActivity;
            boolean shared = this instanceof ExtratoActivity;
            if (Api.perfil.isEmpty() || (!shared && !(parent ? "responsavel" : "aluno").equals(Api.perfil))) {
                startActivity(new android.content.Intent(this, TelaInicialActivity.class).addFlags(android.content.Intent.FLAG_ACTIVITY_CLEAR_TOP));
                finish();
            }
        }
    }
    @Override public void setContentView(int layout) {
        super.setContentView(layout);
        CarrinhoStore.inicializar(this);
        View root = findViewById(R.id.main);
        if (root == null) return;
        final int left = root.getPaddingLeft(), top = root.getPaddingTop();
        final int right = root.getPaddingRight(), bottom = root.getPaddingBottom();
        ViewCompat.setOnApplyWindowInsetsListener(root, (view, windowInsets) -> {
            Insets insets = windowInsets.getInsets(WindowInsetsCompat.Type.systemBars()
                    | WindowInsetsCompat.Type.displayCutout() | WindowInsetsCompat.Type.ime());
            view.setPadding(left + insets.left, top + insets.top, right + insets.right, bottom + insets.bottom);
            return windowInsets;
        });
        ViewCompat.requestApplyInsets(root);
    }
}
