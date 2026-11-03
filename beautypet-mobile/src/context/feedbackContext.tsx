import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useRef,
  useState,
} from "react";

import {
  AccessibilityInfo,
  ActivityIndicator,
  Animated,
  Modal,
  Pressable,
  StyleSheet,
  Text,
  View,
} from "react-native";

// ============================================================
// HEURÍSTICA 1 (visibilidade do status) + HEURÍSTICA 5 (prevenção de erros)
//
// executar(mensagem, tarefa):
//   - mostra uma tela por cima de tudo com "Salvando..." enquanto a tarefa roda;
//   - essa tela bloqueia toques e o botão Voltar do Android, então o mesmo dado
//     não é enviado duas vezes para o banco;
//   - se a pessoa tocar duas vezes MUITO rápido (antes de a tela aparecer), o
//     segundo toque é ignorado pelo "ocupadoRef" (um useState ainda não estaria
//     atualizado nesse instante);
//   - depois de 8 segundos avisa que a internet parece lenta.
//
// mostrarToast(texto, tipo):
//   - aviso flutuante na parte de baixo da tela, some sozinho em 4 segundos.
//   - usado para SUCESSO. Erros continuam em Alert, porque a pessoa precisa ler e
//     tocar em OK (HEURÍSTICA 9).
// ============================================================

type TipoToast = "sucesso" | "info";

type FeedbackContextType = {
  executar: <T>(mensagem: string, tarefa: () => Promise<T>) => Promise<T>;
  mostrarToast: (texto: string, tipo?: TipoToast) => void;
  ocupado: boolean;
};

const AVISO_LENTO_MS = 8000;
const DURACAO_TOAST_MS = 4000;

const FeedbackContext = createContext<FeedbackContextType | undefined>(
  undefined
);

export function FeedbackProvider({ children }: { children: React.ReactNode }) {
  const ocupadoRef = useRef(false);
  const [ocupado, setOcupado] = useState(false);
  const [mensagem, setMensagem] = useState("");
  const [lento, setLento] = useState(false);

  const [toast, setToast] = useState<{ texto: string; tipo: TipoToast } | null>(
    null
  );
  const opacidade = useRef(new Animated.Value(0)).current;
  const temporizadorToast = useRef<ReturnType<typeof setTimeout> | undefined>(
    undefined
  );

  const executar = useCallback(
    async <T,>(texto: string, tarefa: () => Promise<T>): Promise<T> => {
      // Já existe um envio em andamento: este toque é ignorado de propósito.
      // A promessa nunca termina, então o código depois do "await" na tela
      // também não roda (nem o Alert de sucesso, nem o finally).
      if (ocupadoRef.current) {
        return new Promise<T>(() => {});
      }

      ocupadoRef.current = true;
      setMensagem(texto);
      setLento(false);
      setOcupado(true);
      AccessibilityInfo.announceForAccessibility?.(texto);

      const temporizadorLento = setTimeout(() => setLento(true), AVISO_LENTO_MS);

      try {
        return await tarefa();
      } finally {
        clearTimeout(temporizadorLento);
        ocupadoRef.current = false;
        setOcupado(false);
      }
    },
    []
  );

  const esconderToast = useCallback(() => {
    clearTimeout(temporizadorToast.current);
    Animated.timing(opacidade, {
      toValue: 0,
      duration: 250,
      useNativeDriver: true,
    }).start(() => setToast(null));
  }, [opacidade]);

  const mostrarToast = useCallback(
    (texto: string, tipo: TipoToast = "sucesso") => {
      clearTimeout(temporizadorToast.current);
      setToast({ texto, tipo });
      AccessibilityInfo.announceForAccessibility?.(texto);

      Animated.timing(opacidade, {
        toValue: 1,
        duration: 200,
        useNativeDriver: true,
      }).start();

      temporizadorToast.current = setTimeout(esconderToast, DURACAO_TOAST_MS);
    },
    [opacidade, esconderToast]
  );

  useEffect(() => () => clearTimeout(temporizadorToast.current), []);

  return (
    <FeedbackContext.Provider value={{ executar, mostrarToast, ocupado }}>
      {children}

      {toast && (
        <Animated.View
          pointerEvents="box-none"
          style={[
            styles.toastArea,
            {
              opacity: opacidade,
              transform: [
                {
                  translateY: opacidade.interpolate({
                    inputRange: [0, 1],
                    outputRange: [16, 0],
                  }),
                },
              ],
            },
          ]}
        >
          <Pressable
            onPress={esconderToast}
            accessibilityRole="alert"
            accessibilityHint="Toque para fechar o aviso"
            style={[
              styles.toast,
              toast.tipo === "sucesso" ? styles.toastSucesso : styles.toastInfo,
            ]}
          >
            <Text style={styles.toastIcone}>
              {toast.tipo === "sucesso" ? "✓" : "i"}
            </Text>
            <Text style={styles.toastTexto}>{toast.texto}</Text>
          </Pressable>
        </Animated.View>
      )}

      {/* Tela de espera: bloqueia toques e o Voltar do Android enquanto grava. */}
      <Modal
        visible={ocupado}
        transparent
        animationType="fade"
        statusBarTranslucent
        onRequestClose={() => {}}
      >
        <View style={styles.fundo} accessibilityViewIsModal>
          <View style={styles.caixa} accessibilityLiveRegion="assertive">
            <ActivityIndicator size="large" color="#EF3038" />
            <Text style={styles.titulo}>{mensagem}</Text>
            <Text style={styles.dica}>Não feche o aplicativo.</Text>
            {lento && (
              <Text style={styles.lento}>
                A internet parece lenta. Continue aguardando: tocar de novo pode
                enviar os dados duas vezes.
              </Text>
            )}
          </View>
        </View>
      </Modal>
    </FeedbackContext.Provider>
  );
}

export function useFeedback() {
  const context = useContext(FeedbackContext);

  if (!context) {
    throw new Error("useFeedback deve ser usado dentro de FeedbackProvider");
  }

  return context;
}

const styles = StyleSheet.create({
  fundo: {
    flex: 1,
    alignItems: "center",
    justifyContent: "center",
    padding: 24,
    backgroundColor: "rgba(0, 0, 0, 0.45)",
  },
  caixa: {
    width: "100%",
    maxWidth: 320,
    alignItems: "center",
    gap: 10,
    paddingVertical: 28,
    paddingHorizontal: 22,
    borderRadius: 16,
    backgroundColor: "#FFFFFF",
    elevation: 6,
  },
  titulo: {
    fontFamily: "Poppins_600SemiBold",
    fontSize: 16,
    color: "#222222",
    textAlign: "center",
  },
  dica: {
    fontFamily: "Poppins_400Regular",
    fontSize: 12,
    color: "#666666",
    textAlign: "center",
  },
  lento: {
    fontFamily: "Poppins_400Regular",
    fontSize: 12,
    color: "#7A5600",
    backgroundColor: "#FFF7D6",
    borderRadius: 8,
    padding: 8,
    textAlign: "center",
  },
  toastArea: {
    position: "absolute",
    left: 16,
    right: 16,
    bottom: 32,
    alignItems: "center",
  },
  toast: {
    width: "100%",
    maxWidth: 480,
    flexDirection: "row",
    alignItems: "center",
    gap: 10,
    paddingVertical: 14,
    paddingHorizontal: 16,
    borderRadius: 12,
    borderLeftWidth: 6,
    backgroundColor: "#FFFFFF",
    elevation: 6,
    shadowColor: "#000000",
    shadowOpacity: 0.18,
    shadowRadius: 10,
    shadowOffset: { width: 0, height: 6 },
  },
  toastSucesso: { borderLeftColor: "#1F9D55", backgroundColor: "#F0FBF4" },
  toastInfo: { borderLeftColor: "#2F6FBF", backgroundColor: "#F2F7FD" },
  toastIcone: {
    fontFamily: "Poppins_600SemiBold",
    fontSize: 18,
    color: "#1F9D55",
  },
  toastTexto: {
    flex: 1,
    fontFamily: "Poppins_500Medium",
    fontSize: 14,
    color: "#222222",
  },
});
