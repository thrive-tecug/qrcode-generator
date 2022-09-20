
import 'bootstrap/dist/css/bootstrap.min.css';
import Button from 'react-bootstrap/Button';
import Form from 'react-bootstrap/Form';
import React, { Component } from "react";
import axios from 'axios';

 class Home extends Component{
    constructor(props){
        super(props)
        this.state = {
            user: '',
            count: '',
            length: '',
            serial_string: ''
        }  
    }
    changeHandler = (e) =>{
        this.setState({[e.target.name]: e.target.value})
    }
    submitHandler = e => {
        e.preventDefault()
        console.log(this.state)

        //axios.post('http://127.0.0.1:8000/qrcode', this.state ) 
        axios.post('https://4962-102-222-68-171.ap.ngrok.io/qrcode', this.state )

        .then(response =>{
            console.log(response)
        })
        .catch(error => {
            console.log(error)
        })

    }
    render(){
        const {user, count, length, serial_string } = this.state 
        return(
            <div>

<Form onSubmit={this.submitHandler} >
        <Form.Group className="mb-3" controlId="user">
          <Form.Label >User Name</Form.Label>
          <Form.Control type="text" placeholder="Enter User Name" name="user" value={user} onChange={this.changeHandler} />
        </Form.Group>

        <Form.Group className="mb-3" controlId="count">
          <Form.Label>Number Of QrCodes</Form.Label>
          <Form.Control type="number" placeholder="Enter Number Of QrCodes" name="count" value={count} onChange={this.changeHandler} />
        </Form.Group>
  
        <Form.Group className="mb-3" controlId="length">
          <Form.Label>Length Of QrCode</Form.Label>
          <Form.Control type="number" placeholder="Enter Length Of QrCode" name="length" value={length} onChange={this.changeHandler} />
        </Form.Group>

        <Form.Group className="mb-3" controlId="serial_string">
          <Form.Label >Serial </Form.Label>
          <Form.Control type="text" placeholder="Enter serial Characters" name="serial_string" value={serial_string} onChange={this.changeHandler} />
        </Form.Group>



        <Button variant="dark" type="submit">
          Submit
        </Button>
      </Form>

               
            </div>
        )
    }
}

export default Home