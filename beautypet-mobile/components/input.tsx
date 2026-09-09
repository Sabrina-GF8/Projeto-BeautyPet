// Importa os componentes e tipos necessários do React Native.
import { StyleSheet, TextInput, TextInputProps } from "react-native"


// Cria um componente de campo de entrada reutilizável.
// O TextInputProps permite receber todas as propriedades padrão de um TextInput.
export function Input({...rest }: TextInputProps) {

    // Retorna o campo de texto utilizando o estilo definido.
    // O {...rest} permite passar propriedades como placeholder,
    // keyboardType, secureTextEntry, entre outras.
    return <TextInput style={styles.input} {...rest } />

}


// Cria os estilos utilizados pelo componente Input.
const styles = StyleSheet.create({

    // Define o estilo principal do campo de entrada.
    input: {

        // Faz o campo ocupar toda a largura disponível.
        width: "100%",

        // Define a altura do campo.
        height: 52,

        // Define a espessura da borda.
        borderWidth: 1,

        // Define a cor da borda.
        borderColor: "#000000",

        // Define a cor de fundo do campo.
        backgroundColor:"#f5e8e8",

        // Arredonda os cantos do campo.
        borderRadius: 10,

        // Define o tamanho do texto digitado.
        fontSize: 14,

        // Define o espaço interno do lado esquerdo.
        paddingLeft: 18,

        // Define a fonte utilizada no campo.
        fontFamily: "Poppins_600SemiBold",

        // Define o espaço abaixo do campo.
        marginBottom: 20,

        // Move o campo verticalmente para baixo.
        transform: [{ translateY: 50 }],
   
    },
    
})
