import {
  ActivityIndicator,
  StyleSheet,
  Text,
  TouchableOpacity,
  TouchableOpacityProps,
  View,
} from "react-native";

type ButtonProps = TouchableOpacityProps & {
  label: string;
  // HEURÍSTICA 1: mostra a bolinha girando e trava o botão enquanto a ação roda.
  carregando?: boolean;
  textoCarregando?: string;
};

export function Button({
  label,
  style,
  disabled,
  carregando = false,
  textoCarregando,
  ...rest
}: ButtonProps) {
  const bloqueado = disabled || carregando;

  return (
    <TouchableOpacity
      {...rest}
      style={[styles.container, bloqueado && styles.disabled, style]}
      disabled={bloqueado}
      activeOpacity={0.7}
      accessibilityRole="button"
      accessibilityState={{ disabled: bloqueado, busy: carregando }}
    >
      {carregando ? (
        <View style={styles.linha}>
          <ActivityIndicator color="#FFFFFF" />
          <Text style={styles.label}>{textoCarregando || label}</Text>
        </View>
      ) : (
        <Text style={styles.label}>{label}</Text>
      )}
    </TouchableOpacity>
  );
}

const styles = StyleSheet.create({
  container: {
    width: "100%",
    height: 48,
    backgroundColor: "#07219e",
    alignItems: "center",
    justifyContent: "center",
    borderRadius: 10,
    marginTop: 65,
  },
  linha: {
    flexDirection: "row",
    alignItems: "center",
    gap: 10,
  },
  label: {
    color: "#FFFFFF",
    fontSize: 16,
    fontWeight: "600",
  },
  disabled: {
    opacity: 0.6,
  },
});
