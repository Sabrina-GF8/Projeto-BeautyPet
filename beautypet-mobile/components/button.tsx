// Importa os componentes e tipos necessários do React Native.
import { StyleSheet, Text, TouchableOpacity, TouchableOpacityProps } from "react-native"


// Define as propriedades que o componente Button pode receber.
// TouchableOpacityProps permite utilizar as propriedades padrão de um botão.
// A propriedade label será obrigatória e receberá o texto do botão.
type ButtonProps = TouchableOpacityProps & {
    label: string
}


// Cria um componente de botão reutilizável.
export function Button({label, ...rest}: ButtonProps){

   // Retorna o botão e o texto que será exibido dentro dele.
   return(
    <TouchableOpacity
        style = {styles.container}
        activeOpacity={0.5}
        {...rest}
    >

       // Exibe o texto recebido através da propriedade label.
       <Text style={styles.label}>{label}</Text>

    </TouchableOpacity>
   )

}


// Cria os estilos utilizados pelo componente Button.
const styles = StyleSheet.create({

    // Estilo do botão.
    container:{

        // Faz o botão ocupar toda a largura disponível.
        width: "100%",

        // Define a altura do botão.
        height: 48,

        // Define a cor de fundo do botão.
        backgroundColor:"#07219e",

        // Centraliza o conteúdo horizontalmente.
        alignItems: "center",

        // Centraliza o conteúdo verticalmente.
        justifyContent: "center",

        // Arredonda os cantos do botão.
        borderRadius: 10,

        // Define o espaço acima do botão.
        marginTop: 65,
    },


    // Estilo do texto exibido dentro do botão.
    label: {

        // Define a cor branca para o texto.
        color: "#FFFFFF",

        // Define o tamanho da fonte.
        fontSize: 16,

        // Define a espessura da fonte.
        fontWeight: "600",

    },
})
