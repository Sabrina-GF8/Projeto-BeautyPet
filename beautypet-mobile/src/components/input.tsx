// Componente de entrada reutilizável do Beauty Pet.
import { StyleSheet, TextInput, TextInputProps } from "react-native";

type InputProps = TextInputProps & {
  // Mantido porque as telas originais de Login/Cadastro já usam esta propriedade.
  // O layout atual do campo não desenha o ícone; a propriedade é apenas aceita
  // para preservar a interface existente sem erro de TypeScript.
  icon?: string;
};

export function Input({ icon: _icon, ...rest }: InputProps) {
  return <TextInput style={styles.input} {...rest} />;
}

const styles = StyleSheet.create({
  input: {
    width: "100%",
    height: 52,
    borderWidth: 1,
    borderColor: "#000000",
    backgroundColor: "#f5e8e8",
    borderRadius: 10,
    fontSize: 14,
    paddingLeft: 18,
    fontFamily: "Poppins_600SemiBold",
    marginBottom: 20,
    transform: [{ translateY: 50 }],
  },
});
